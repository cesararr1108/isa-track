<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\DeliverOrder;

use Isa\Shipping\Application\Port\EvidenceFile;

final class DeliverOrderCommand
{
    /**
     * @param  list<array{method: string, amount: int, reference?: ?string}>  $payments
     */
    public function __construct(
        public readonly string $orderId,
        public readonly ?string $receiverName,
        public readonly ?string $receiverDocument = null,
        public readonly ?EvidenceFile $photo = null,
        public readonly ?EvidenceFile $signature = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly array $payments = [],
        public readonly ?string $note = null,
        public readonly ?string $deliveredBy = null,
        public readonly ?string $actorCarrierId = null,
    ) {}
}
