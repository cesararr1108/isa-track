<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use DateTimeImmutable;
use Isa\Shared\Domain\ValueObject\Money;

/** Resultado de una entrega exitosa: prueba + pagos recibidos. */
final class Delivery
{
    /** @param list<Payment> $payments */
    public function __construct(
        public readonly DeliveryProof $proof,
        public readonly array $payments,
        public readonly DateTimeImmutable $deliveredAt,
    ) {}

    public function collectedTotal(): Money
    {
        return array_reduce($this->payments, fn (Money $sum, Payment $p) => $sum->add($p->amount), Money::zero());
    }
}
