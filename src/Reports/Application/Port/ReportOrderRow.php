<?php

declare(strict_types=1);

namespace Isa\Reports\Application\Port;

/** Un pedido tal como lo necesitan los reportes (fechas ya en hora local, Y-m-d). */
final class ReportOrderRow
{
    public function __construct(
        public readonly string $id,
        public readonly string $trackingCode,
        public readonly string $createdOn,
        public readonly string $status,
        public readonly ?string $carrierId,
        public readonly ?string $zoneId,
        public readonly ?string $neighborhood,
        public readonly string $recipientName,
        public readonly int $deliveryFee,
        public readonly int $cashOnDelivery,
        public readonly ?string $deliveredOn,
        /** null = sin fecha prometida o no entregado. */
        public readonly ?bool $onTime,
    ) {}
}
