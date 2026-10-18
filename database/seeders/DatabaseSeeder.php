<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Isa\Companies\Application\CreateCompany\CreateCompanyCommand;
use Isa\Companies\Application\CreateCompany\CreateCompanyHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierCommand;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Shared\Infrastructure\Tenancy\RequestTenantContext;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierCommand;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierHandler;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusCommand;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusHandler;
use Isa\Shipping\Application\CreateOrder\CreateOrderCommand;
use Isa\Shipping\Application\CreateOrder\CreateOrderHandler;
use Isa\Shipping\Application\DeliverOrder\DeliverOrderCommand;
use Isa\Shipping\Application\DeliverOrder\DeliverOrderHandler;

/**
 * Datos de demostración. Usa los mismos casos de uso que la API:
 * así se ve que la lógica no depende de HTTP.
 *
 * Usuarios (contraseña "password"):
 *   super@isa.test, admin@demo.test, despacho@demo.test,
 *   conductor@demo.test, gerencia@demo.test
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = app(CreateCompanyHandler::class)(new CreateCompanyCommand(
            name: 'Distribuidora El Poblado',
            taxId: '900123456-7',
            primaryColor: '#1d4ed8',
            secondaryColor: '#f59e0b',
        ));

        // Los casos de uso privados trabajan dentro de una empresa.
        app(RequestTenantContext::class)->set($companyId);

        $driverCarrier = app(RegisterCarrierHandler::class)(new RegisterCarrierCommand(
            'Juan Pérez', 'own_driver', '+57 300 111 2233', 'ABC12D',
        ));
        app(RegisterCarrierHandler::class)(new RegisterCarrierCommand(
            'Mensajería Express Antioquia', 'external_company', '+57 604 444 5566',
        ));

        User::factory()->create(['name' => 'Superadmin', 'email' => 'super@isa.test', 'role' => Role::SuperAdmin]);
        $users = [
            ['Ana Admin', 'admin@demo.test', Role::CompanyAdmin, null],
            ['Diego Despacho', 'despacho@demo.test', Role::Dispatcher, null],
            ['Juan Pérez', 'conductor@demo.test', Role::Driver, $driverCarrier->value()],
            ['Gloria Gerencia', 'gerencia@demo.test', Role::Viewer, null],
        ];
        foreach ($users as [$name, $email, $role, $carrierId]) {
            User::factory()->create([
                'name' => $name, 'email' => $email, 'role' => $role,
                'company_id' => $companyId->value(), 'carrier_id' => $carrierId,
            ]);
        }

        // [nombre, teléfono, dirección, barrio, lat, lng, valor contra entrega]
        $orders = [
            ['Laura Gómez', '+57 301 222 3344', 'Cra 43A # 1-50', 'El Poblado', 6.2088, -75.5679, null],
            ['Carlos Restrepo', '+57 302 555 6677', 'Cl 10 # 32-15', 'Laureles', 6.2442, -75.5966, 85000],
            ['María Ochoa', '+57 315 888 9900', 'Cra 70 # 44-20', 'Estadio', 6.2531, -75.5888, 120000],
            ['Andrés Mejía', '+57 318 444 1100', 'Cl 33 # 65-12', 'Belén', 6.2309, -75.5906, 60000],
        ];
        foreach ($orders as $i => [$name, $phone, $line, $neighborhood, $lat, $lng, $cod]) {
            $order = app(CreateOrderHandler::class)(new CreateOrderCommand(
                recipientName: $name, recipientPhone: $phone, recipientEmail: null,
                addressLine: $line, city: 'Medellín', neighborhood: $neighborhood,
                addressReference: 'Portería', latitude: $lat, longitude: $lng,
                packages: [['description' => 'Caja mediana', 'weight_kg' => 2.5]],
                externalReference: 'WEB-100'.$i,
                promisedAt: now()->addDay()->setTime(17, 0)->toIso8601String(),
                cashOnDelivery: $cod,
            ));

            if ($i !== 2) {
                app(AssignCarrierHandler::class)(new AssignCarrierCommand($order->id()->value(), $driverCarrier->value()));
            }
            if ($i === 0 || $i === 3) {
                app(ChangeOrderStatusHandler::class)(new ChangeOrderStatusCommand(
                    orderId: $order->id()->value(), status: 'in_route', latitude: 6.2150, longitude: -75.5700,
                ));
            }
            if ($i === 3) {
                // Entrega contra entrega: parte en efectivo, parte por Nequi.
                // El seeder no adjunta foto ni firma, así que relaja ese requisito.
                config(['isa.delivery_proof.require.photo' => false, 'isa.delivery_proof.require.signature' => false]);
                app(DeliverOrderHandler::class)(new DeliverOrderCommand(
                    orderId: $order->id()->value(),
                    receiverName: 'Andrés Mejía',
                    latitude: $lat, longitude: $lng,
                    payments: [
                        ['method' => 'cash', 'amount' => 40000],
                        ['method' => 'nequi', 'amount' => 20000, 'reference' => 'M123456'],
                    ],
                ));
            }

            $this->command?->info("Pedido {$order->trackingCode()} → ".url('/rastreo/'.$order->trackingCode()));
        }
    }
}
