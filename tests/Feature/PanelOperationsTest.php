<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Routing\Infrastructure\Persistence\RouteModel;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Shipping\Infrastructure\Persistence\OrderModel;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** Panel web, etapa 2: pedidos, rutas y mapa en vivo. */
final class PanelOperationsTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    public function test_dispatcher_creates_assigns_and_moves_orders_from_the_panel(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        $dispatcher = $this->user($company, Role::Dispatcher);

        $this->actingAs($dispatcher)->post('/pedidos', $this->orderPayload([
            'address' => ['line' => 'Cra 43A # 1-50', 'latitude' => 6.2088, 'longitude' => -75.5679],
        ]))->assertRedirect()->assertSessionHas('success');
        $order = OrderModel::query()->firstOrFail();

        $this->actingAs($dispatcher)->get("/pedidos?order={$order->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('orders/pages/OrdersIndex')
            ->has('orders', 1)
            ->where('orders.0.latitude', 6.2088)
            ->where('selected.tracking_code', $order->tracking_code)
            ->where('carriers.0.id', $carrierId));

        $this->actingAs($dispatcher)->post("/pedidos/{$order->id}/asignar", ['carrier_id' => $carrierId])->assertSessionHas('success');
        $this->actingAs($dispatcher)->post("/pedidos/{$order->id}/estado", ['status' => 'prepared'])->assertSessionHas('success');
        self::assertSame('prepared', $order->fresh()->status);

        // "Entregado" no se puede marcar desde el panel; las reglas del dominio vuelven como mensaje.
        $this->actingAs($dispatcher)->post("/pedidos/{$order->id}/estado", ['status' => 'delivered'])->assertSessionHasErrors('status');
        $this->actingAs($dispatcher)->from('/pedidos')->post("/pedidos/{$order->id}/estado", ['status' => 'in_route'])
            ->assertRedirect('/pedidos')->assertSessionHasErrors('general');
        $this->actingAs($dispatcher)->post("/pedidos/{$order->id}/estado", ['status' => 'incident'])->assertSessionHasErrors('incident_reason');
    }

    public function test_viewer_cannot_create_and_other_companies_orders_are_hidden(): void
    {
        $a = $this->company('A');
        $viewer = $this->user($a, Role::Viewer);
        $this->actingAs($viewer)->post('/pedidos', $this->orderPayload())->assertForbidden();

        $b = $this->company('B');
        $this->actingAs($this->user($b, Role::Dispatcher))->post('/pedidos', $this->orderPayload());
        $other = OrderModel::query()->withoutGlobalScope('company')->firstOrFail();

        $this->actingAs($viewer)->get("/pedidos?order={$other->id}")->assertInertia(fn (Assert $p) => $p->has('orders', 0)->where('selected', null));
        $this->actingAs($this->user($a, Role::Dispatcher))->post("/pedidos/{$other->id}/estado", ['status' => 'prepared'])->assertNotFound();
    }

    public function test_dispatcher_plans_reorders_and_starts_a_route_from_the_panel(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company);
        $dispatcher = $this->user($company, Role::Dispatcher);
        $near = $this->orderAt($dispatcher, 6.215);
        $far = $this->orderAt($dispatcher, 6.25);
        $today = now()->format('Y-m-d');

        $this->actingAs($dispatcher)->get('/rutas')->assertInertia(fn (Assert $p) => $p
            ->component('routes/pages/RoutesIndex')->has('routable', 2)->has('carriers', 1)->where('selected', null));

        $this->actingAs($dispatcher)->post('/rutas', ['carrier_id' => $carrierId, 'date' => $today, 'order_ids' => [$far, $near], 'optimize' => true])
            ->assertRedirect()->assertSessionHas('success');
        $route = RouteModel::query()->firstOrFail();

        $this->actingAs($dispatcher)->get("/rutas?date={$today}&route={$route->id}")->assertInertia(fn (Assert $p) => $p
            ->has('routable', 0)
            ->where('selected.id', $route->id)
            ->where('selected.stops.0.order_id', $near));

        $this->actingAs($dispatcher)->put("/rutas/{$route->id}/paradas", ['order_ids' => [$far, $near]])->assertRedirect();
        $this->actingAs($dispatcher)->post("/rutas/{$route->id}/optimizar")->assertSessionHas('success');
        $this->actingAs($dispatcher)->post("/rutas/{$route->id}/estado", ['status' => 'in_progress'])->assertSessionHas('success');
        self::assertSame('in_progress', $route->fresh()->status);

        // Ya en curso no se puede reordenar.
        $this->actingAs($dispatcher)->put("/rutas/{$route->id}/paradas", ['order_ids' => [$near, $far]])->assertSessionHasErrors('general');
        // No se programan rutas en días pasados.
        $this->actingAs($dispatcher)->post('/rutas', ['carrier_id' => $carrierId, 'date' => '2020-01-01', 'order_ids' => [$near]])->assertSessionHasErrors('date');
    }

    public function test_driver_sees_only_own_routes_and_cannot_plan(): void
    {
        $company = $this->company('Tienda Uno');
        $mine = $this->carrierWithCapacity($company);
        $other = $this->carrierWithCapacity($company);
        $dispatcher = $this->user($company, Role::Dispatcher);
        $this->actingAs($dispatcher)->post('/rutas', ['carrier_id' => $other, 'date' => now()->format('Y-m-d'), 'order_ids' => [$this->orderAt($dispatcher, 6.22)]]);
        $route = RouteModel::query()->firstOrFail();

        $driver = $this->user($company, Role::Driver, $mine);
        $this->actingAs($driver)->get('/rutas')->assertInertia(fn (Assert $p) => $p->has('routes', 0)->has('routable', 0)->has('carriers', 0));
        $this->actingAs($driver)->post("/rutas/{$route->id}/estado", ['status' => 'in_progress'])->assertNotFound();
        $this->actingAs($driver)->post('/rutas', ['carrier_id' => $mine, 'date' => now()->format('Y-m-d'), 'order_ids' => []])->assertForbidden();
    }

    public function test_live_map_shows_recent_rider_locations_reported_by_the_driver_app(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrierWithCapacity($company);
        $driver = $this->user($company, Role::Driver, $carrierId);

        Sanctum::actingAs($driver);
        $this->postJson('/api/me/location', ['latitude' => 6.215, 'longitude' => -75.57])->assertNoContent();
        $this->postJson('/api/me/location', ['latitude' => 99, 'longitude' => 0])->assertUnprocessable();

        $dispatcher = $this->user($company, Role::Dispatcher);
        $this->actingAs($dispatcher)->post('/pedidos', $this->orderPayload(['address' => ['line' => 'X', 'latitude' => 6.2, 'longitude' => -75.57]]));
        $this->actingAs($dispatcher)->get('/mapa')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('live/pages/LiveMap')
            ->has('orders', 1)
            ->where('carriers.0.id', $carrierId)
            ->where('carriers.0.location.latitude', 6.215));

        // Una ubicación de hace más de 3 horas ya no se muestra.
        CarrierModel::query()->whereKey($carrierId)->update(['last_location_at' => now()->subHours(4)]);
        $this->actingAs($dispatcher)->get('/mapa')->assertInertia(fn (Assert $p) => $p->has('carriers', 0));
    }

    private function carrierWithCapacity(CompanyId $company): string
    {
        return CarrierModel::query()->create([
            'company_id' => $company->value(), 'name' => 'Juan Pérez', 'type' => 'own_driver', 'active' => true,
            'vehicle_plate' => 'ABC12D', 'vehicle_type' => 'motorcycle', 'capacity_kg' => 50, 'capacity_packages' => 20,
        ])->id;
    }

    private function orderAt(mixed $user, float $lat): string
    {
        $this->actingAs($user)->post('/pedidos', $this->orderPayload([
            'address' => ['line' => 'Cl 1', 'latitude' => $lat, 'longitude' => -75.585],
            'packages' => [['description' => 'Caja', 'weight_kg' => 2]],
        ]))->assertSessionHasNoErrors();

        return OrderModel::query()->latest('created_at')->latest('id')->firstOrFail()->id;
    }
}
