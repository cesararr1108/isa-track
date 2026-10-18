<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use Isa\Shipping\Domain\Exception\DeliveryProofIncomplete;

/** Qué exige cada empresa como prueba de entrega (configurable en config/isa.php). */
final class DeliveryProofRequirements
{
    public function __construct(
        public readonly bool $receiverName = true,
        public readonly bool $photo = true,
        public readonly bool $signature = true,
        public readonly bool $location = false,
    ) {}

    /** @throws DeliveryProofIncomplete */
    public function assertSatisfiedBy(DeliveryProof $proof): void
    {
        $missing = array_keys(array_filter([
            'receiver_name' => $this->receiverName && trim((string) $proof->receiverName) === '',
            'photo' => $this->photo && $proof->photoPath === null,
            'signature' => $this->signature && $proof->signaturePath === null,
            'location' => $this->location && $proof->location === null,
        ]));

        if ($missing !== []) {
            throw new DeliveryProofIncomplete($missing);
        }
    }
}
