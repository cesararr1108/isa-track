<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/** Paquete dentro de un pedido. Su código va en la etiqueta (QR / código de barras). */
final class Package
{
    public function __construct(
        public readonly PackageId $id,
        public readonly string $code,
        public readonly string $description,
        public readonly ?float $weightKg = null,
    ) {}
}
