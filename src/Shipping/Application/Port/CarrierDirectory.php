<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Lo que Pedidos necesita saber de los transportadores. Pedidos no depende
 * del módulo Flota directamente: un adaptador implementa este puerto.
 */
interface CarrierDirectory
{
    public function isAvailable(CompanyId $companyId, CarrierId $carrierId): bool;

    public function displayName(CompanyId $companyId, CarrierId $carrierId): ?string;

    /**
     * Lo que el cliente final puede ver del mensajero que le lleva el pedido.
     *
     * @return array{name: string, vehicle_type: string|null, vehicle_plate: string|null, photo_path: string|null}|null
     */
    public function publicProfile(CompanyId $companyId, CarrierId $carrierId): ?array;
}
