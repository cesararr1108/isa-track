<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DeliveryProofIncomplete extends DomainException
{
    private const LABELS = [
        'receiver_name' => 'nombre de quien recibe',
        'photo' => 'foto',
        'signature' => 'firma',
        'location' => 'ubicación GPS',
    ];

    /** @param list<string> $missing claves técnicas (receiver_name, photo, signature, location) */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('Falta información en la prueba de entrega: '
            .implode(', ', array_map(fn (string $m) => self::LABELS[$m] ?? $m, $missing)).'.');
    }

    public function errorCode(): string
    {
        return 'delivery_proof_incomplete';
    }
}
