<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\CreateOrder;

/**
 * Datos de entrada del caso de uso. Es un objeto simple (DTO): no sabe de
 * HTTP, así que se puede crear desde un controlador, un comando de consola
 * o un importador de Excel/Shopify.
 */
final class CreateOrderCommand
{
    /**
     * @param  list<array{description: string, weight_kg?: float|null}>  $packages
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        public readonly ?string $recipientEmail,
        public readonly string $addressLine,
        public readonly string $city,
        public readonly ?string $neighborhood,
        public readonly ?string $addressReference,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly array $packages,
        public readonly ?string $externalReference = null,
        public readonly ?string $promisedAt = null,
        public readonly ?string $createdBy = null,
        /** Valor a cobrar contra entrega en pesos (null = ya pagado). */
        public readonly ?int $cashOnDelivery = null,
    ) {}
}
