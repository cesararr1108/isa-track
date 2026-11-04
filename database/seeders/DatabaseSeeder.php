<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Isa\Companies\Application\CreateCompany\CreateCompanyCommand;
use Isa\Companies\Application\CreateCompany\CreateCompanyHandler;
use Isa\Fleet\Application\CarrierData;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Fleet\Application\ReportLocation\ReportLocationHandler;
use Isa\Routing\Application\PlanRoute\PlanRouteCommand;
use Isa\Routing\Application\PlanRoute\PlanRouteHandler;
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
use Isa\Zones\Application\SaveZone\SaveZoneCommand;
use Isa\Zones\Application\SaveZone\SaveZoneHandler;

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
            primaryColor: '#0B1F3A',
            secondaryColor: '#00C896',
        ));

        // Los casos de uso privados trabajan dentro de una empresa.
        app(RequestTenantContext::class)->set($companyId);

        $driverCarrier = app(RegisterCarrierHandler::class)(new CarrierData(
            name: 'Juan Pérez', type: 'own_driver', phone: '+57 300 111 2233', vehiclePlate: 'ABC12D',
            documentNumber: '1037654321', vehicleType: 'motorcycle', capacityKg: 40, capacityPackages: 15,
        ));
        app(RegisterCarrierHandler::class)(new CarrierData(
            name: 'Camila Rendón', type: 'own_driver', phone: '+57 310 222 4455', vehiclePlate: 'JKL345',
            documentNumber: '43987123', vehicleType: 'van', capacityKg: 600, capacityPackages: 80,
        ));
        app(RegisterCarrierHandler::class)(new CarrierData(
            name: 'Mensajería Express Antioquia', type: 'external_company', phone: '+57 604 444 5566',
        ));

        // Zonas de cobertura con su tarifa de domicilio: [nombre, color, tarifa, minutos, polígono [lat, lng]]
        $zones = [
            ['El Poblado', '#00C896', 8000, 45, [[6.2250, -75.5800], [6.2240, -75.5530], [6.2000, -75.5450], [6.1850, -75.5560], [6.1870, -75.5790]]],
            ['Laureles - Estadio', '#3B82F6', 7000, 40, [[6.2660, -75.6100], [6.2650, -75.5800], [6.2420, -75.5790], [6.2350, -75.5950], [6.2380, -75.6110]]],
            ['Belén', '#A855F7', 7500, 50, [[6.2350, -75.6150], [6.2340, -75.5850], [6.2050, -75.5860], [6.2000, -75.6120]]],
            ['Centro - Boston', '#FFB800', 6000, 35, [[6.2620, -75.5760], [6.2600, -75.5500], [6.2380, -75.5480], [6.2370, -75.5770]]],
            ['Envigado', '#F97316', 10000, 60, [[6.1850, -75.6000], [6.1850, -75.5600], [6.1500, -75.5550], [6.1480, -75.6000]]],
        ];
        foreach ($zones as [$name, $color, $fee, $minutes, $polygon]) {
            app(SaveZoneHandler::class)(new SaveZoneCommand($name, $color, $polygon, $fee, $minutes));
        }

        // Sin factories: así el seeder funciona en producción (composer install --no-dev no trae Faker).
        $demoUser = fn (array $attrs) => User::query()->create($attrs + ['password' => 'password', 'active' => true])
            ->forceFill(['email_verified_at' => now()])->save();

        $demoUser(['name' => 'Superadmin', 'email' => 'super@isa.test', 'role' => Role::SuperAdmin]);
        $users = [
            ['Ana Admin', 'admin@demo.test', Role::CompanyAdmin, null],
            ['Diego Despacho', 'despacho@demo.test', Role::Dispatcher, null],
            ['Juan Pérez', 'conductor@demo.test', Role::Driver, $driverCarrier->value()],
            ['Gloria Gerencia', 'gerencia@demo.test', Role::Viewer, null],
        ];
        foreach ($users as [$name, $email, $role, $carrierId]) {
            $demoUser([
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
            ['Paula Zapata', '+57 300 777 2211', 'Cra 43 # 38 Sur-35', 'Envigado', 6.1710, -75.5870, null],
            ['Sergio Arango', '+57 311 909 3030', 'Cl 52 # 40-18', 'Boston', 6.2477, -75.5604, 45000],
        ];
        $pending = [];
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

            if ($i >= 4) {
                $pending[] = $order->id()->value();
            } elseif ($i !== 2) {
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

        // Juan va en camino: su app reporta la ubicación (mapa en vivo).
        app(ReportLocationHandler::class)($driverCarrier->value(), 6.2150, -75.5700);

        // Ruta programada para mañana con los pedidos pendientes (se ordena sola).
        $camila = collect(app(ListCarriersHandler::class)())->firstWhere('name', 'Camila Rendón');
        app(PlanRouteHandler::class)(new PlanRouteCommand(
            carrierId: $camila['id'],
            date: now()->addDay()->format('Y-m-d'),
            orderIds: $pending,
            name: 'Ruta sur-oriente',
        ));
    }
}
