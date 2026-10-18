<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Isa\Shipping\Domain\TrackingCode;
use Isa\Shipping\Domain\TrackingCodeGenerator;

/**
 * Genera códigos tipo "ISA-7K2M9QXP". Sin 0/O/1/I para que se puedan
 * dictar por teléfono sin confusiones. Prefijo y largo en config/isa.php.
 */
final class RandomTrackingCodeGenerator implements TrackingCodeGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly string $prefix,
        private readonly int $length,
    ) {}

    public function generate(): TrackingCode
    {
        $code = '';
        for ($i = 0; $i < $this->length; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return new TrackingCode($this->prefix !== '' ? $this->prefix.'-'.$code : $code);
    }
}
