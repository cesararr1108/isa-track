<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** Pruebas de punta a punta por HTTP (Laravel + base de datos). */
final class OrderFlowTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    public function test_dispatcher_creates_assigns_and_driver_delivers_order(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        $dispatcher = $this->user($company, Role::Dispatcher);
        $driver = $this->user($company, Role::Driver, $carrierId);

        Sanctum::actingAs($dispatcher);
        $order = $this->postJson('/api/orders', $this->orderPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonCount(2, 'data.packages')
            ->json('data');

        $this->postJson("/api/orders/{$order['id']}/assign", ['carrier_id' => $carrierId])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        Sanctum::actingAs($driver);
        $this->postJson("/api/orders/{$order['id']}/status", ['status' => 'in_route', 'latitude' => 6.2442, 'longitude' => -75.5966])
            ->assertOk();
        $this->postJson("/api/orders/{$order['id']}/deliver", [
            'receiver_name' => 'Laura Gómez',
            'photo' => UploadedFile::fake()->image('paquete.jpg'),
            'signature' => UploadedFile::fake()->image('firma.png'),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonCount(4, 'data.history');

        $this->getJson("/api/public/tracking/{$order['tracking_code']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.company.name', 'Tienda Uno')
            ->assertJsonPath('data.recipient_first_name', 'Laura')
            ->assertJsonPath('data.received_by', 'Laura')
            ->assertJsonMissingPath('data.recipient_phone');

        $this->get("/rastreo/{$order['tracking_code']}")->assertOk()->assertSee('Entregado');
    }

    public function test_invalid_transition_returns_business_error(): void
    {
        $company = $this->company('Tienda Uno');
        Sanctum::actingAs($this->user($company, Role::Dispatcher));
        $id = $this->postJson('/api/orders', $this->orderPayload())->json('data.id');

        $this->postJson("/api/orders/{$id}/status", ['status' => 'in_route'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_status_transition');
    }

    public function test_companies_cannot_see_each_other_orders(): void
    {
        $companyA = $this->company('A');
        $companyB = $this->company('B');

        Sanctum::actingAs($this->user($companyA, Role::Dispatcher));
        $id = $this->postJson('/api/orders', $this->orderPayload())->json('data.id');

        Sanctum::actingAs($this->user($companyB, Role::Dispatcher));
        $this->getJson("/api/orders/{$id}")->assertNotFound();
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_driver_cannot_move_orders_of_another_carrier(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierA = $this->carrier($company);
        $carrierB = $this->carrier($company);

        Sanctum::actingAs($this->user($company, Role::Dispatcher));
        $id = $this->postJson('/api/orders', $this->orderPayload())->json('data.id');
        $this->postJson("/api/orders/{$id}/assign", ['carrier_id' => $carrierA])->assertOk();

        Sanctum::actingAs($this->user($company, Role::Driver, $carrierB));
        $this->postJson("/api/orders/{$id}/status", ['status' => 'in_route'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'order_not_assigned_to_actor');
    }

    public function test_permissions_come_from_config(): void
    {
        $company = $this->company('Tienda Uno');

        Sanctum::actingAs($this->user($company, Role::Viewer));
        $this->postJson('/api/orders', $this->orderPayload())->assertForbidden();
        $this->getJson('/api/orders')->assertOk();

        config(['isa.roles.viewer' => ['orders.*']]);
        $this->app->forgetInstance(PermissionChecker::class);
        $this->postJson('/api/orders', $this->orderPayload())->assertCreated();
    }

    public function test_customer_is_notified_on_creation(): void
    {
        Log::spy();
        $company = $this->company('Tienda Uno');
        Sanctum::actingAs($this->user($company, Role::Dispatcher));

        $code = $this->postJson('/api/orders', $this->orderPayload())->json('data.tracking_code');

        Log::shouldHaveReceived('info')->withArgs(
            fn ($msg, $ctx) => $ctx['phone'] === '+57 300 123 4567' && str_contains($ctx['message'], $code),
        );
    }

    public function test_login_returns_token(): void
    {
        $user = $this->user($this->company('Tienda Uno'), Role::Dispatcher);

        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/auth/me')->assertJsonPath('user.role', 'dispatcher');
    }
}
