<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

final class CarriersAndZonesTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    private const POBLADO = [[6.2250, -75.5800], [6.2240, -75.5530], [6.2000, -75.5450], [6.1850, -75.5560], [6.1870, -75.5790]];

    public function test_admin_registers_carrier_with_full_profile_and_photo(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        Sanctum::actingAs($this->user($company, Role::CompanyAdmin));

        $carrier = $this->postJson('/api/carriers', [
            'name' => 'Juan Pérez', 'type' => 'own_driver', 'document_number' => '1.037.654.321',
            'phone' => '+57 300 111 2233', 'vehicle_plate' => 'abc-12d', 'vehicle_type' => 'motorcycle',
            'capacity_kg' => 40, 'capacity_packages' => 15,
        ])->assertCreated()
            ->assertJsonPath('data.document_number', '1037654321')
            ->assertJsonPath('data.vehicle_plate', 'ABC12D')
            ->assertJsonPath('data.capacity_kg', 40)
            ->assertJsonPath('data.has_photo', false)
            ->json('data');

        $this->postJson('/api/carriers', ['name' => 'Otro', 'type' => 'own_driver', 'document_number' => '1037654321'])
            ->assertUnprocessable()->assertJsonPath('error', 'duplicate_carrier_document');

        $this->post("/api/carriers/{$carrier['id']}/photo", ['photo' => UploadedFile::fake()->image('juan.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.has_photo', true);
        $this->get("/api/carriers/{$carrier['id']}/photo")->assertOk();

        // Reemplazar la foto borra la anterior.
        $this->post("/api/carriers/{$carrier['id']}/photo", ['photo' => UploadedFile::fake()->image('juan2.png', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        self::assertCount(1, Storage::disk('local')->allFiles('carriers'));

        $this->putJson("/api/carriers/{$carrier['id']}", ['name' => 'Juan P.', 'type' => 'own_driver', 'vehicle_type' => 'car', 'vehicle_plate' => 'XYZ987'])
            ->assertOk()->assertJsonPath('data.vehicle_type', 'car')->assertJsonPath('data.name', 'Juan P.');

        $this->postJson("/api/carriers/{$carrier['id']}/active", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    }

    public function test_driver_uploads_own_photo_but_cannot_edit_carriers(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        Sanctum::actingAs($this->user($company, Role::Driver, $carrierId));

        $this->post('/api/me/photo', ['photo' => UploadedFile::fake()->image('yo.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.id', $carrierId)->assertJsonPath('data.has_photo', true);
        $this->getJson('/api/auth/me')->assertJsonPath('user.carrier.photo_url', url("/api/carriers/{$carrierId}/photo"));
        $this->putJson("/api/carriers/{$carrierId}", ['name' => 'X', 'type' => 'own_driver'])->assertForbidden();
    }

    public function test_zones_set_delivery_fee_of_new_orders(): void
    {
        $company = $this->company('Tienda Uno');
        Sanctum::actingAs($this->user($company, Role::CompanyAdmin));

        $zone = $this->postJson('/api/zones', ['name' => 'El Poblado', 'color' => '#00C896', 'delivery_fee' => 8000, 'estimated_minutes' => 45, 'polygon' => self::POBLADO])
            ->assertCreated()->assertJsonCount(5, 'data.polygon')->json('data');
        $this->postJson('/api/zones', ['name' => 'el poblado', 'delivery_fee' => 1, 'polygon' => self::POBLADO])
            ->assertUnprocessable()->assertJsonPath('error', 'duplicate_zone_name');

        $this->getJson('/api/zones/quote?latitude=6.2088&longitude=-75.5679')->assertOk()
            ->assertJsonPath('data.zone_name', 'El Poblado')->assertJsonPath('data.delivery_fee', 8000);
        $this->getJson('/api/zones/quote?latitude=6.2442&longitude=-75.5966')
            ->assertUnprocessable()->assertJsonPath('error', 'location_outside_coverage');

        $order = $this->postJson('/api/orders', $this->orderPayload(['address' => ['line' => 'Cra 43A # 1-50', 'latitude' => 6.2088, 'longitude' => -75.5679]]))
            ->assertCreated()->assertJsonPath('data.delivery_fee', 8000)->assertJsonPath('data.zone.name', 'El Poblado')->json('data');
        $this->getJson("/api/orders/{$order['id']}")->assertJsonPath('data.zone.id', $zone['id']);

        // Tarifa manual tiene prioridad; fuera de zona sin tarifa queda en null.
        $this->postJson('/api/orders', $this->orderPayload(['delivery_fee' => 5000, 'address' => ['line' => 'X', 'latitude' => 6.2088, 'longitude' => -75.5679]]))
            ->assertJsonPath('data.delivery_fee', 5000);
        $this->postJson('/api/orders', $this->orderPayload())->assertJsonPath('data.delivery_fee', null)->assertJsonPath('data.zone', null);

        $this->putJson("/api/zones/{$zone['id']}", ['name' => 'El Poblado', 'delivery_fee' => 9000, 'polygon' => self::POBLADO])
            ->assertOk()->assertJsonPath('data.delivery_fee', 9000);
        $this->deleteJson("/api/zones/{$zone['id']}")->assertNoContent();
        $this->getJson("/api/orders/{$order['id']}")->assertOk()->assertJsonPath('data.delivery_fee', 8000)->assertJsonPath('data.zone', null);
    }

    public function test_zones_are_per_company_and_dispatcher_only_reads(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        Sanctum::actingAs($this->user($a, Role::CompanyAdmin));
        $id = $this->postJson('/api/zones', ['name' => 'Z', 'delivery_fee' => 1000, 'polygon' => self::POBLADO])->json('data.id');

        Sanctum::actingAs($this->user($b, Role::CompanyAdmin));
        $this->getJson('/api/zones')->assertJsonCount(0, 'data');
        $this->putJson("/api/zones/{$id}", ['name' => 'Z', 'delivery_fee' => 1, 'polygon' => self::POBLADO])->assertNotFound();

        Sanctum::actingAs($this->user($a, Role::Dispatcher));
        $this->getJson('/api/zones')->assertJsonCount(1, 'data');
        $this->postJson('/api/zones', ['name' => 'Y', 'delivery_fee' => 1, 'polygon' => self::POBLADO])->assertForbidden();
    }

    public function test_prototype_is_served_with_a_web_address(): void
    {
        $this->get('/prototipo')->assertOk()->assertSee('<!doctype html>', false)->assertSee('ISA Track Medellín');
    }
}
