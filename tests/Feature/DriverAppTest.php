<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Shipping\Infrastructure\Persistence\OrderModel;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** App del mensajero (PWA en /mensajero): su día, salir a entregar, novedad, entrega y GPS. */
final class DriverAppTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    private CompanyId $company;

    private string $carrierId;

    private User $driver;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = $this->company('Tienda Uno');
        $this->carrierId = $this->carrier($this->company);
        $this->driver = $this->user($this->company, Role::Driver, $this->carrierId);
        $this->dispatcher = $this->user($this->company, Role::Dispatcher);
    }

    public function test_driver_lands_on_the_app_after_login_and_from_the_panel_home(): void
    {
        $this->post('/login', ['email' => $this->driver->email, 'password' => 'password'])->assertRedirect('/mensajero');
        $this->actingAs($this->driver)->get('/')->assertRedirect('/mensajero');
        // Desde el panel (visita de Inertia) se fuerza carga completa: así el celular lee el manifiesto.
        $version = (string) app(HandleInertiaRequests::class)->version(request());
        $this->actingAs($this->driver)->get('/', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/mensajero'));

        $this->post('/logout');
        $this->post('/login', ['email' => $this->dispatcher->email, 'password' => 'password'])->assertRedirect('/');
    }

    public function test_home_shows_only_my_orders_and_my_cash(): void
    {
        $mine = $this->assignedOrder($this->carrierId, 50000);
        $otherCarrier = CarrierModel::query()->create(['company_id' => $this->company->value(), 'name' => 'Otra', 'type' => 'own_driver', 'active' => true])->id;
        $this->assignedOrder($otherCarrier);

        $this->actingAs($this->driver)->get('/mensajero')->assertOk()
            ->assertSee('/mensajero-app/manifest.json', false)
            ->assertInertia(fn (Assert $p) => $p
                ->component('driver/pages/DriverHome')
                ->where('linked', true)
                ->has('orders', 1)
                ->where('orders.0.id', $mine)
                ->where('orders.0.cash_on_delivery', 50000)
                ->where('cash.collected.expected_cash', 0));

        // El panel no se ofrece para instalar como app: solo /mensajero.
        $this->actingAs($this->dispatcher)->get('/pedidos')->assertOk()->assertDontSee('/mensajero-app/manifest.json', false);
    }

    public function test_driver_without_carrier_sees_a_clear_message_and_viewer_cannot_enter(): void
    {
        $unlinked = $this->user($this->company, Role::Driver);
        $this->actingAs($unlinked)->get('/mensajero')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('linked', false)->has('orders', 0)->where('cash', null));

        $this->actingAs($this->user($this->company, Role::Viewer))->get('/mensajero')->assertForbidden();
    }

    public function test_order_of_another_carrier_or_company_is_not_found(): void
    {
        $other = CarrierModel::query()->create(['company_id' => $this->company->value(), 'name' => 'Otra', 'type' => 'own_driver', 'active' => true])->id;
        $notMine = $this->assignedOrder($other);
        $mine = $this->assignedOrder($this->carrierId);

        $this->actingAs($this->driver)->get("/mensajero/pedidos/{$notMine}")->assertNotFound();
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$notMine}/estado", ['status' => 'in_route'])->assertSessionHasErrors('general');
        self::assertSame('assigned', OrderModel::query()->findOrFail($notMine)->status);

        $this->actingAs($this->driver)->get("/mensajero/pedidos/{$mine}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('driver/pages/DriverOrder')
            ->where('order.id', $mine)
            ->where('proofRequired.photo', true)
            ->has('incidentReasons'));

        $b = $this->company('Otra empresa');
        $foreignDriver = $this->user($b, Role::Driver, $this->carrier($b));
        $this->actingAs($foreignDriver)->get("/mensajero/pedidos/{$mine}")->assertNotFound();
    }

    public function test_full_day_in_route_incident_retry_and_delivery_with_split_payment(): void
    {
        $id = $this->assignedOrder($this->carrierId, 85000);
        $gps = ['latitude' => 6.2442, 'longitude' => -75.5812];

        $this->actingAs($this->driver)->from("/mensajero/pedidos/{$id}")
            ->post("/mensajero/pedidos/{$id}/estado", ['status' => 'in_route'] + $gps)
            ->assertRedirect("/mensajero/pedidos/{$id}")->assertSessionHas('success');

        // Novedad: exige motivo.
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/estado", ['status' => 'incident'])->assertSessionHasErrors('incident_reason');
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/estado", ['status' => 'incident', 'incident_reason' => 'customer_absent', 'note' => 'No abren'])
            ->assertSessionHas('success');
        // Desde la app no se puede marcar entregado ni devolver por estado.
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/estado", ['status' => 'delivered'])->assertSessionHasErrors('status');
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/estado", ['status' => 'in_route'])->assertSessionHas('success');

        // Entrega incompleta: el dominio la rechaza y no se guarda nada.
        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/entregar", ['receiver_name' => 'Carlos'])
            ->assertSessionHasErrors('general');
        self::assertSame('in_route', OrderModel::query()->findOrFail($id)->status);

        $this->actingAs($this->driver)->post("/mensajero/pedidos/{$id}/entregar", [
            'receiver_name' => 'Carlos Restrepo',
            'photo' => UploadedFile::fake()->image('entrega.jpg', 800, 600),
            'signature' => UploadedFile::fake()->image('firma.png', 400, 180),
            'payments' => [
                ['method' => 'cash', 'amount' => 50000],
                ['method' => 'nequi', 'amount' => 35000, 'reference' => 'M778812'],
            ],
        ] + $gps)->assertRedirect('/mensajero')->assertSessionHas('success');

        self::assertSame('delivered', OrderModel::query()->findOrFail($id)->status);
        self::assertCount(2, Storage::disk('local')->allFiles("evidence/{$this->company}/{$id}"));

        $this->actingAs($this->driver)->get('/mensajero')->assertInertia(fn (Assert $p) => $p
            ->where('cash.collected.expected_cash', 50000)
            ->where('cash.collected.total', 85000));
        $this->actingAs($this->driver)->get("/mensajero/pedidos/{$id}")->assertInertia(fn (Assert $p) => $p
            ->where('order.delivery.receiver_name', 'Carlos Restrepo')
            ->where('order.delivery.photo_url', route('panel.orders.evidence', [$id, 'photo'])));
    }

    public function test_location_from_the_app_updates_the_live_map(): void
    {
        $this->actingAs($this->driver)->postJson('/mensajero/ubicacion', ['latitude' => 6.25, 'longitude' => -75.56])->assertNoContent();
        $carrier = CarrierModel::query()->findOrFail($this->carrierId);
        self::assertEqualsWithDelta(6.25, (float) $carrier->last_latitude, 0.0001);

        $this->actingAs($this->driver)->postJson('/mensajero/ubicacion', ['latitude' => 120])->assertUnprocessable();
        $this->actingAs($this->user($this->company, Role::Viewer))->postJson('/mensajero/ubicacion', ['latitude' => 6.25, 'longitude' => -75.56])->assertForbidden();
    }

    private function assignedOrder(string $carrierId, ?int $cashOnDelivery = null): string
    {
        $this->actingAs($this->dispatcher)->post('/pedidos', $this->orderPayload([
            'cash_on_delivery' => $cashOnDelivery,
            'address' => ['line' => 'Cl 10 # 32-15', 'latitude' => 6.21, 'longitude' => -75.57],
        ]))->assertSessionHas('success');
        $id = OrderModel::query()->latest('created_at')->latest('id')->firstOrFail()->id;
        $this->actingAs($this->dispatcher)->post("/pedidos/{$id}/asignar", ['carrier_id' => $carrierId])->assertSessionHas('success');

        return $id;
    }
}
