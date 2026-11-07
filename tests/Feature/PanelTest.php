<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Zones\Infrastructure\Persistence\ZoneModel;
use Tests\Feature\Concerns\BuildsTenantData;
use Tests\TestCase;

/** Panel web (Inertia): sesión, permisos, seguridad y pantallas de la etapa 1. */
final class PanelTest extends TestCase
{
    use BuildsTenantData, RefreshDatabase;

    private const POLY = [[6.2250, -75.5800], [6.2240, -75.5530], [6.2000, -75.5450], [6.1850, -75.5560], [6.1870, -75.5790]];

    public function test_guests_are_sent_to_login_and_pages_have_security_headers(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/transportadores')->assertRedirect('/login');

        $res = $this->get('/login')->assertOk()->assertInertia(fn (Assert $p) => $p->component('auth/pages/Login'));
        $csp = (string) $res->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_history_is_encrypted_only_in_a_secure_context(): void
    {
        // http://IP-del-servidor: el navegador no permite cifrar, así que no se pide.
        $plain = $this->get('http://192.168.2.68/login')->assertOk();
        self::assertArrayNotHasKey('encryptHistory', $plain->viewData('page'));
        $plain->assertHeaderMissing('Cross-Origin-Opener-Policy');

        $secure = $this->get('https://isatrack.co/login')->assertOk();
        self::assertTrue($secure->viewData('page')['encryptHistory'] ?? false);
        $secure->assertHeader('Strict-Transport-Security');
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors(['email' => 'El campo correo es obligatorio.']);
        $this->postJson('/api/auth/login', ['email' => 'no-es-correo'])
            ->assertJsonPath('errors.email.0', 'El campo correo debe ser un correo electrónico válido.')
            ->assertJsonPath('errors.password.0', 'El campo contraseña es obligatorio.')
            ->assertJsonPath('message', 'El campo correo debe ser un correo electrónico válido. (y 1 error más)');
    }

    public function test_login_regenerates_session_and_logout_invalidates_it(): void
    {
        $user = $this->user($this->company('Tienda Uno'), Role::CompanyAdmin);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password_inactive_users_and_throttles(): void
    {
        $company = $this->company('Tienda Uno');
        $inactive = User::factory()->create(['company_id' => $company->value(), 'role' => Role::Dispatcher, 'active' => false]);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $user = $this->user($company, Role::Dispatcher);
        foreach (range(1, 5) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mala-clave'])->assertSessionHasErrors('email');
        }
        // Aun con la clave correcta, después de 5 intentos fallidos queda bloqueado un minuto.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        self::assertStringStartsWith('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_shared_props_expose_only_safe_user_data_and_menu_by_permission(): void
    {
        $company = $this->company('Tienda Uno');
        $driver = $this->user($company, Role::Driver, $this->carrier($company));

        // El mensajero no tiene resumen: va a su app del celular.
        $this->actingAs($driver)->get('/')->assertRedirect('/mensajero');

        $this->actingAs($driver)->get('/pedidos')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('orders/pages/OrdersIndex')
            ->where('auth.user.email', $driver->email)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->where('company.name', 'Tienda Uno')
            ->where('navigation', fn ($nav) => ! collect($nav)->flatMap(fn ($g) => $g['items'])->pluck('key')->contains('carriers')));

        $this->actingAs($driver)->get('/transportadores')->assertForbidden();
        $this->actingAs($driver)->post('/zonas', ['name' => 'X', 'delivery_fee' => 1, 'polygon' => self::POLY])->assertForbidden();
    }

    public function test_admin_manages_carriers_from_the_panel(): void
    {
        Storage::fake('local');
        $company = $this->company('Tienda Uno');
        $admin = $this->user($company, Role::CompanyAdmin);

        $this->actingAs($admin)->from('/transportadores')->post('/transportadores', [
            'name' => 'Luisa Gil', 'type' => 'own_driver', 'document_number' => '43.111.222',
            'vehicle_type' => 'motorcycle', 'vehicle_plate' => 'xyz-12a', 'capacity_kg' => '35',
            'photo' => UploadedFile::fake()->image('luisa.jpg', 300, 300),
        ])->assertRedirect('/transportadores')->assertSessionHas('success');

        $carrier = CarrierModel::query()->where('name', 'Luisa Gil')->firstOrFail();
        self::assertSame('XYZ12A', $carrier->vehicle_plate);
        self::assertNotNull($carrier->photo_path);
        $this->actingAs($admin)->get("/transportadores/{$carrier->id}/foto")->assertOk()->assertHeader('Cache-Control', 'max-age=300, private');

        // Un error de negocio vuelve al formulario con el mensaje.
        $this->actingAs($admin)->from('/transportadores')->post('/transportadores', ['name' => 'Otra', 'type' => 'own_driver', 'document_number' => '43111222'])
            ->assertRedirect('/transportadores')->assertSessionHasErrors(['general' => 'Ya existe un mensajero con la cédula 43111222.']);
        // Archivos que no son imagen se rechazan antes de guardar nada.
        $this->actingAs($admin)->from('/transportadores')->post("/transportadores/{$carrier->id}", [
            'name' => 'Luisa Gil', 'type' => 'own_driver', 'photo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($admin)->post("/transportadores/{$carrier->id}/estado", ['active' => false])->assertSessionHas('success');
        self::assertFalse(CarrierModel::query()->find($carrier->id)->active);

        $this->actingAs($admin)->get('/transportadores')->assertInertia(fn (Assert $p) => $p
            ->component('fleet/pages/CarriersIndex')
            ->has('carriers', 1)
            ->where('carriers.0.photo_url', route('panel.carriers.photo', $carrier->id))
            ->where('vehicleTypes.motorcycle', 'Moto'));
    }

    public function test_carriers_of_another_company_are_not_reachable(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $carrierB = $this->carrier($b);

        $admin = $this->user($a, Role::CompanyAdmin);
        $this->actingAs($admin)->get('/transportadores')->assertInertia(fn (Assert $p) => $p->has('carriers', 0));
        $this->actingAs($admin)->post("/transportadores/{$carrierB}", ['name' => 'Hack', 'type' => 'own_driver'])->assertNotFound();
        $this->actingAs($admin)->get("/transportadores/{$carrierB}/foto")->assertNotFound();
    }

    public function test_admin_manages_zones_and_quotes_from_the_panel(): void
    {
        $company = $this->company('Tienda Uno');
        $admin = $this->user($company, Role::CompanyAdmin);

        $this->actingAs($admin)->from('/zonas')->post('/zonas', ['name' => 'El Poblado', 'color' => '#00C896', 'delivery_fee' => 8000, 'polygon' => self::POLY])
            ->assertRedirect('/zonas')->assertSessionHas('success');
        $zone = ZoneModel::query()->firstOrFail();

        $this->actingAs($admin)->getJson('/zonas/cotizar?latitude=6.2088&longitude=-75.5679')->assertOk()->assertJsonPath('data.delivery_fee', 8000);
        $this->actingAs($admin)->getJson('/zonas/cotizar?latitude=6.30&longitude=-75.70')->assertOk()->assertJsonPath('data', null);

        $this->actingAs($admin)->from('/zonas')->post('/zonas', ['name' => 'Mala', 'delivery_fee' => 1, 'polygon' => [[0, 0], [0, 1]]])->assertSessionHasErrors('polygon');

        $this->actingAs($admin)->put("/zonas/{$zone->id}", ['name' => 'El Poblado', 'delivery_fee' => 9000, 'polygon' => self::POLY, 'active' => false])->assertSessionHas('success');
        self::assertSame(9000, ZoneModel::query()->find($zone->id)->delivery_fee);

        $this->actingAs($admin)->get('/zonas')->assertInertia(fn (Assert $p) => $p->component('zones/pages/ZonesIndex')->where('zones.0.active', false));
        $this->actingAs($admin)->delete("/zonas/{$zone->id}")->assertSessionHas('success');
        self::assertSame(0, ZoneModel::query()->count());
    }

    public function test_superadmin_without_company_cannot_use_the_panel(): void
    {
        $super = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($super)->get('/')->assertForbidden();
    }
}
