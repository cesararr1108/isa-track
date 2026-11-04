<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** Panel web, etapa 3: caja, reportes, usuarios y permisos, bitácora. */
final class PanelAdminTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    public function test_cash_page_shows_collections_and_closes_the_register(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        $dispatcher = $this->user($company, Role::Dispatcher);
        $driver = $this->user($company, Role::Driver, $carrierId);

        // Una entrega contra entrega de $50.000 (30.000 efectivo + 20.000 Nequi).
        Sanctum::actingAs($dispatcher);
        $id = $this->postJson('/api/orders', $this->orderPayload(['cash_on_delivery' => 50000]))->json('data.id');
        $this->postJson("/api/orders/{$id}/assign", ['carrier_id' => $carrierId]);
        Sanctum::actingAs($driver);
        $this->postJson("/api/orders/{$id}/status", ['status' => 'in_route']);
        $this->post("/api/orders/{$id}/deliver", [
            'receiver_name' => 'Laura', 'photo' => UploadedFile::fake()->image('p.jpg', 300, 300), 'signature' => UploadedFile::fake()->image('f.png', 300, 100),
            'payments' => [['method' => 'cash', 'amount' => 30000], ['method' => 'nequi', 'amount' => 20000, 'reference' => 'M1']],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($dispatcher)->get('/caja')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('cash/pages/CashIndex')
            ->where('rows.0.carrier.id', $carrierId)
            ->where('rows.0.collected.total', 50000)
            ->where('rows.0.collected.expected_cash', 30000)
            ->where('rows.0.closing', null));

        // Con diferencia exige nota.
        $this->actingAs($dispatcher)->from('/caja')->post('/caja/cierres', ['carrier_id' => $carrierId, 'date' => now()->format('Y-m-d'), 'cash_received' => 25000])
            ->assertSessionHasErrors('general');
        $this->actingAs($dispatcher)->post('/caja/cierres', ['carrier_id' => $carrierId, 'date' => now()->format('Y-m-d'), 'cash_received' => 25000, 'notes' => 'Faltan 5.000'])
            ->assertSessionHas('success', 'Caja cerrada con faltante.');
        self::assertTrue(DB::table('audit_logs')->where('action', 'cash.closed')->exists());

        // El mensajero ve solo su caja y no puede cerrarla.
        $this->actingAs($driver)->get('/caja')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.closing.result', 'short'));
        $this->actingAs($driver)->post('/caja/cierres', ['carrier_id' => $carrierId, 'date' => now()->format('Y-m-d'), 'cash_received' => 0])->assertForbidden();
    }

    public function test_reports_page_and_csv_download(): void
    {
        $company = $this->company('Tienda Uno');
        $admin = $this->user($company, Role::CompanyAdmin);
        $this->actingAs($admin)->post('/pedidos', $this->orderPayload(['recipient' => ['name' => '=CMD()', 'phone' => '+57 300 111 2222']]));
        $today = now()->format('Y-m-d');

        $this->actingAs($admin)->get("/reportes?from={$today}&to={$today}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('reports/pages/ReportsIndex')
            ->where('report.totals.orders', 1)
            ->where('filters.from', $today)
            ->has('report.by_day', 1));
        $this->actingAs($admin)->get("/reportes?from={$today}&to=2020-01-01")->assertSessionHasErrors('to');

        $csv = $this->actingAs($admin)->get("/reportes/pedidos.csv?from={$today}&to={$today}")->assertOk()->assertHeader('Cache-Control', 'no-store, private')->streamedContent();
        self::assertStringContainsString("'=CMD()", $csv, 'Un nombre que empieza por "=" se escapa para que Excel no lo ejecute');

        $this->actingAs($this->user($company, Role::Driver, $this->carrier($company)))->get('/reportes')->assertForbidden();
    }

    public function test_admin_manages_users_permissions_and_sees_the_audit_log(): void
    {
        $company = $this->company('Tienda Uno');
        $carrierId = $this->carrier($company);
        $admin = $this->user($company, Role::CompanyAdmin);

        $this->actingAs($admin)->post('/usuarios', ['name' => 'D', 'email' => 'd@x.co', 'password' => 'corta', 'role' => 'dispatcher'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/usuarios', ['name' => 'M', 'email' => 'm@x.co', 'password' => 'clave12345', 'role' => 'driver'])->assertSessionHasErrors('carrier_id');
        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Diego', 'email' => 'diego@x.co', 'password' => 'clave12345', 'role' => 'dispatcher', 'revoked_permissions' => ['cash.close'],
        ])->assertSessionHas('success');
        $diego = User::query()->where('email', 'diego@x.co')->firstOrFail();
        self::assertSame(['cash.close'], $diego->revoked_permissions);

        // El permiso quitado se aplica en el panel.
        $this->actingAs($diego)->post('/caja/cierres', ['carrier_id' => $carrierId, 'date' => now()->format('Y-m-d'), 'cash_received' => 0])->assertForbidden();

        $this->actingAs($admin)->put("/usuarios/{$diego->id}", ['role' => 'dispatcher', 'extra_permissions' => ['users.manage'], 'revoked_permissions' => []])->assertSessionHas('success');
        $this->actingAs($diego->fresh())->get('/usuarios')->assertOk();

        // Nadie se desactiva a sí mismo; desactivar a otro le cierra las sesiones.
        $this->actingAs($admin)->put("/usuarios/{$admin->id}", ['active' => false])->assertSessionHasErrors('active');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $diego->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($admin)->put("/usuarios/{$diego->id}", ['active' => false])->assertSessionHas('success');
        self::assertFalse(DB::table('sessions')->where('user_id', $diego->id)->exists());

        $this->actingAs($admin)->get('/usuarios')->assertInertia(fn (Assert $p) => $p
            ->component('users/pages/UsersIndex')
            ->has('users', 2)
            ->where('activity.0.action', 'user.updated')
            ->missing('users.0.password'));

        // Otra empresa no ve ni toca estos usuarios.
        $other = $this->user($this->company('Otra'), Role::CompanyAdmin);
        $this->actingAs($other)->put("/usuarios/{$diego->id}", ['name' => 'Hack'])->assertNotFound();
    }

    public function test_login_attempts_are_audited(): void
    {
        $company = $this->company('Tienda Uno');
        $user = $this->user($company, Role::Dispatcher);
        $this->post('/login', ['email' => $user->email, 'password' => 'mala'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        self::assertSame(['login.failed', 'login.success'], DB::table('audit_logs')->orderBy('id')->pluck('action')->all());
        self::assertStringNotContainsString('mala', (string) DB::table('audit_logs')->value('data'), 'La contraseña nunca se guarda');
    }
}
