<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use DateTimeImmutable;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\UserId;

/** Entrada del historial: qué estado, cuándo, quién y dónde. Es inmutable. */
final class StatusChange
{
    public function __construct(
        public readonly string $id,
        public readonly OrderStatus $status,
        public readonly DateTimeImmutable $occurredAt,
        public readonly ?UserId $changedBy = null,
        public readonly ?GeoPoint $location = null,
        public readonly ?string $note = null,
        public readonly ?IncidentReason $incidentReason = null,
    ) {}
}
