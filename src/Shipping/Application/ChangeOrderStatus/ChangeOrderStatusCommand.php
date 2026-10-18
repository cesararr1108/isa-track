<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\ChangeOrderStatus;

final class ChangeOrderStatusCommand
{
    /**
     * @param  string|null  $actorCarrierId  Si quien cambia el estado es un conductor,
     *                                       su transportador: solo puede mover sus pedidos.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly ?string $changedBy = null,
        public readonly ?string $actorCarrierId = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $note = null,
        public readonly ?string $incidentReason = null,
    ) {}
}
