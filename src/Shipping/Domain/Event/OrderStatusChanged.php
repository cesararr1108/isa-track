<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Event;

use DateTimeImmutable;
use Isa\Shared\Domain\DomainEvent;

/**
 * Se publica en cada cambio de estado. Lleva los datos que necesitan los
 * suscriptores (notificar por WhatsApp, tiempo real, estadísticas) para
 * que no tengan que volver a cargar el pedido.
 */
final class OrderStatusChanged implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $companyId,
        public readonly string $trackingCode,
        public readonly ?string $previousStatus,
        public readonly string $newStatus,
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
