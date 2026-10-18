<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DeliveryProofIncomplete extends DomainException
{
    /** @param list<string> $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('Falta información en la prueba de entrega: '.implode(', ', $missing).'.');
    }

    public function errorCode(): string
    {
        return 'delivery_proof_incomplete';
    }
}
