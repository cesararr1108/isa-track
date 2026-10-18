<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Dirección colombiana: la línea de texto ("Cra 43A # 1-50") más referencias
 * ("portería, apto 302") y, si se conoce, el punto exacto en el mapa.
 */
final class Address
{
    public function __construct(
        public readonly string $line,
        public readonly string $city,
        public readonly ?string $neighborhood = null,
        public readonly ?string $reference = null,
        public readonly ?GeoPoint $location = null,
    ) {
        if (trim($line) === '' || trim($city) === '') {
            throw new InvalidArgumentException('La dirección y la ciudad son obligatorias.');
        }
    }
}
