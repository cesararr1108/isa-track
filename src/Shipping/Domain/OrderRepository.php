<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use Isa\Shared\Domain\ValueObject\CompanyId;

interface OrderRepository
{
    public function save(Order $order): void;

    /** Busca un pedido dentro de una empresa. */
    public function find(CompanyId $companyId, OrderId $id): ?Order;

    /** Busca por código de rastreo sin importar la empresa (rastreo público). */
    public function findByTrackingCode(TrackingCode $code): ?Order;

    public function trackingCodeExists(TrackingCode $code): bool;
}
