<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\ValueObject\Money;

/** Un pago recibido por el conductor al entregar (puede haber varios: parte efectivo, parte Nequi). */
final class Payment
{
    public function __construct(
        public readonly string $id,
        public readonly PaymentMethod $method,
        public readonly Money $amount,
        public readonly ?string $reference = null,
    ) {
        if ($amount->isZero()) {
            throw new InvalidArgumentException('Un pago debe ser mayor a cero.');
        }
    }
}
