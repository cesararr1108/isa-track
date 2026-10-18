<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Prueba de entrega. Las fotos y la firma se guardan como archivos; aquí solo
 * viaja la ruta donde quedaron almacenadas.
 */
final class DeliveryProof
{
    public function __construct(
        public readonly ?string $receiverName,
        public readonly ?string $receiverDocument = null,
        public readonly ?string $photoPath = null,
        public readonly ?string $signaturePath = null,
        public readonly ?GeoPoint $location = null,
    ) {}
}
