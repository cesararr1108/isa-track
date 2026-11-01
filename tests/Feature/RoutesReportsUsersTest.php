<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

final class RoutesReportsUsersTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    public function test_dispatcher_plans_route_that_assigns_orders_and_shows_on_map(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company, 10, 5);
        Sanctum::actingAs($this->user($company, Role::Dispatcher));
        $far = $this->orderAt(6.25, 2);
        $near = $this->orderAt(6.215, 2);
        $mid = $this->orderAt(6.23, 2);

        $route = $this->postJson('/api/routes', ['carrier_id' => $carrierId, 'date' => '2026-11-02', 'order_ids' => [$far, $near, $mid]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.stops_count', 3)
            ->assertJsonPath('data.total_weight_kg', 6)
            ->json('data');

        // Optimizada desde la bodega (6.212, -75.585): primero la más cercana.
        self::assertSame([$near, $mid, $far], array_column($route['stops'], 'order_id'));
        $this->getJson("/api/orders/{$far}")->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.carrier_id', $carrierId);

        $geo = $this->getJson("/api/routes/{$route['id']}/geojson")->assertOk()->json();
        self::assertSame('LineString', $geo['features'][0]['geometry']['type']);
        self::assertCount(4, $geo['features'][0]['geometry']['coordinates']);

        $this->putJson("/api/routes/{$route['id']}/stops", ['order_ids' => [$far, $mid, $near]])
            ->assertOk()->assertJsonPath('data.stops.0.order_id', $far);
        $this->postJson("/api/routes/{$route['id']}/optimize")->assertOk()->assertJsonPath('data.stops.0.order_id', $near);

        // Un pedido no puede estar en dos rutas abiertas.
        $this->postJson('/api/routes', ['carrier_id' => $carrierId, 'order_ids' => [$near]])
            ->assertUnprocessable()->assertJsonPath('error', 'order_already_in_route');

        $this->getJson('/api/routes?date=2026-11-02')->assertJsonCount(1, 'data');
        $this->getJson('/api/routes?date=2026-11-03')->assertJsonCount(0, 'data');
    }

    public function test_route_rules_capacity_location_and_driver_scope(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company, 3, null);
        $other = $this->carrierWithCapacity($company, null, null);
        Sanctum::actingAs($this->user($company, Role::Dispatcher));

        $this->postJson('/api/routes', ['carrier_id' => $carrierId, 'order_ids' => [$this->orderAt(6.22, 2), $this->orderAt(6.23, 2)]])
            ->assertUnprocessable()->assertJsonPath('error', 'route_exceeds_carrier_capacity');

        $noGps = $this->postJson('/api/orders', $this->orderPayload())->json('data.id');
        $this->postJson('/api/routes', ['carrier_id' => $other, 'order_ids' => [$noGps]])
            ->assertUnprocessable()->assertJsonPath('error', 'stop_without_location');

        $routeId = $this->postJson('/api/routes', ['carrier_id' => $other, 'order_ids' => [$this->orderAt(6.22, 1)]])->assertCreated()->json('data.id');

        // El mensajero de otra ruta no la ve; el dueño la inicia y la termina.
        Sanctum::actingAs($this->user($company, Role::Driver, $carrierId));
        $this->getJson('/api/routes')->assertJsonCount(0, 'data');
        $this->getJson("/api/routes/{$routeId}")->assertNotFound();
        $this->postJson("/api/routes/{$routeId}/status", ['status' => 'in_progress'])->assertNotFound();

        Sanctum::actingAs($this->user($company, Role::Driver, $other));
        $this->postJson("/api/routes/{$routeId}/status", ['status' => 'in_progress'])->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/routes/{$routeId}/status", ['status' => 'planned'])->assertUnprocessable()->assertJsonPath('error', 'invalid_route_status_change');
        $this->postJson('/api/routes', ['carrier_id' => $other, 'order_ids' => []])->assertForbidden();
    }

    public function test_report_summary_and_csv_by_date_range(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company, null, null);
        Sanctum::actingAs($this->user($company, Role::CompanyAdmin));
        $this->postJson('/api/zones', ['name' => 'Valle', 'delivery_fee' => 7000, 'polygon' => [[6.30, -75.65], [6.30, -75.50], [6.15, -75.50], [6.15, -75.65]]]);

        $id = $this->orderAt(6.22, 1);
        $this->orderAt(6.23, 1);
        $this->postJson("/api/orders/{$id}/assign", ['carrier_id' => $carrierId])->assertOk();
        $this->postJson("/api/orders/{$id}/status", ['status' => 'in_route'])->assertOk();
        $this->postJson("/api/orders/{$id}/status", ['status' => 'incident', 'incident_reason' => 'customer_absent'])->assertOk();

        $today = now()->format('Y-m-d');
        $report = $this->getJson("/api/reports/summary?from={$today}&to={$today}")->assertOk()->json('data');

        self::assertSame(2, $report['totals']['orders']);
        self::assertSame(14000, $report['totals']['delivery_fees']);
        self::assertSame(1, $report['totals']['incidents_reported']);
        self::assertSame(['customer_absent' => 1], $report['incidents_by_reason']);
        self::assertSame('Valle', $report['by_zone'][0]['name']);
        self::assertCount(1, $report['by_day']);

        $this->getJson('/api/reports/summary?from=2020-01-01&to=2020-01-31')->assertOk()->assertJsonPath('data.totals.orders', 0);
        $this->getJson("/api/reports/summary?from={$today}&to=2020-01-01")->assertUnprocessable();
        $this->getJson("/api/reports/summary?carrier_id={$carrierId}")->assertJsonPath('data.totals.orders', 1);

        $csv = $this->get("/api/reports/orders.csv?from={$today}&to={$today}")->assertOk()->streamedContent();
        self::assertStringContainsString('Código;Creado;Estado', $csv);
        self::assertSame(3, substr_count(trim($csv), "\n") + 1);

        Sanctum::actingAs($this->user($company, Role::Driver, $carrierId));
        $this->getJson('/api/reports/summary')->assertForbidden();
    }

    public function test_admin_manages_users_and_their_permissions(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        $admin = $this->user($company, Role::CompanyAdmin);
        Sanctum::actingAs($admin);

        $catalog = $this->getJson('/api/permissions')->assertOk()->json('data');
        self::assertContains('reports.view', array_column($catalog['permissions'], 'key'));
        self::assertNotContains('superadmin', array_column($catalog['roles'], 'key'));

        $this->postJson('/api/users', ['name' => 'M', 'email' => 'm@x.co', 'password' => 'secreto123', 'role' => 'driver'])
            ->assertUnprocessable();
        $this->postJson('/api/users', ['name' => 'S', 'email' => 's@x.co', 'password' => 'secreto123', 'role' => 'superadmin'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $driver = $this->postJson('/api/users', [
            'name' => 'Juan', 'email' => 'juan@x.co', 'password' => 'secreto123', 'role' => 'driver',
            'carrier_id' => $carrierId, 'extra_permissions' => ['reports.view'],
        ])->assertCreated()->assertJsonPath('data.carrier.id', $carrierId)->json('data');
        self::assertContains('reports.view', $driver['permissions']);
        self::assertContains('routes.drive', $driver['permissions']);

        $dispatcher = $this->postJson('/api/users', [
            'name' => 'Diego', 'email' => 'diego@x.co', 'password' => 'secreto123', 'role' => 'dispatcher',
            'revoked_permissions' => ['cash.close'],
        ])->assertCreated()->json('data');
        self::assertNotContains('cash.close', $dispatcher['permissions']);
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@x.co', 'password' => 'secreto123', 'role' => 'viewer', 'extra_permissions' => ['inventado']])
            ->assertUnprocessable();

        // Los permisos se aplican de verdad.
        Sanctum::actingAs(User::find($driver['id']));
        $this->getJson('/api/reports/summary')->assertOk();
        Sanctum::actingAs(User::find($dispatcher['id']));
        $this->postJson('/api/cash/closings', ['carrier_id' => $carrierId, 'cash_received' => 0])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();

        // Desactivar bloquea el acceso.
        Sanctum::actingAs($admin);
        $this->putJson("/api/users/{$dispatcher['id']}", ['active' => false, 'revoked_permissions' => []])->assertOk()->assertJsonPath('data.active', false);
        $this->putJson("/api/users/{$admin->id}", ['active' => false])->assertUnprocessable();
        Sanctum::actingAs(User::find($dispatcher['id']));
        $this->getJson('/api/orders')->assertForbidden();
        $this->postJson('/api/auth/login', ['email' => 'diego@x.co', 'password' => 'secreto123'])->assertUnprocessable();

        // Otra empresa no ve estos usuarios.
        Sanctum::actingAs($this->user($this->company('Otra'), Role::CompanyAdmin));
        $this->getJson('/api/users')->assertJsonCount(1, 'data');
        $this->putJson("/api/users/{$driver['id']}", ['name' => 'Hack'])->assertNotFound();
    }

    public function test_public_tracking_shows_carrier_to_customer_while_on_the_way(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company, null, null);
        Sanctum::actingAs($this->user($company, Role::CompanyAdmin));
        $this->post("/api/carriers/{$carrierId}/photo", ['photo' => UploadedFile::fake()->image('j.jpg')], ['Accept' => 'application/json'])->assertOk();
        $order = $this->postJson('/api/orders', $this->orderPayload(['address' => ['line' => 'Cra 43A', 'latitude' => 6.2088, 'longitude' => -75.5679]]))->json('data');
        $this->postJson("/api/orders/{$order['id']}/assign", ['carrier_id' => $carrierId]);

        $this->get("/api/public/tracking/{$order['tracking_code']}/carrier-photo")->assertNotFound();
        $this->postJson("/api/orders/{$order['id']}/status", ['status' => 'in_route', 'latitude' => 6.2101, 'longitude' => -75.5702]);

        $this->getJson("/api/public/tracking/{$order['tracking_code']}")
            ->assertJsonPath('data.carrier.first_name', 'Juan')
            ->assertJsonPath('data.carrier.vehicle_plate', 'ABC12D')
            ->assertJsonPath('data.carrier.has_photo', true)
            ->assertJsonPath('data.destination_point.latitude', 6.21)
            ->assertJsonMissingPath('data.carrier.document_number');
        $this->get("/api/public/tracking/{$order['tracking_code']}/carrier-photo")->assertOk();
        $this->get("/rastreo/{$order['tracking_code']}")->assertOk()->assertSee('En camino')->assertSee('ABC12D');
    }

    private function carrierWithCapacity(CompanyId $company, ?float $kg, ?int $packages): string
    {
        return CarrierModel::query()->create([
            'company_id' => $company->value(), 'name' => 'Juan Pérez', 'type' => 'own_driver', 'active' => true,
            'vehicle_plate' => 'ABC12D', 'vehicle_type' => 'motorcycle', 'capacity_kg' => $kg, 'capacity_packages' => $packages,
        ])->id;
    }

    private function orderAt(float $lat, float $kg): string
    {
        return $this->postJson('/api/orders', $this->orderPayload([
            'address' => ['line' => 'Cl 1', 'latitude' => $lat, 'longitude' => -75.585],
            'packages' => [['description' => 'Caja', 'weight_kg' => $kg]],
        ]))->assertCreated()->json('data.id');
    }
}
