<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use InvalidArgumentException;

/** Cliente final que recibe el pedido. */
final class Recipient
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $email = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('El nombre del destinatario es obligatorio.');
        }
        if (! preg_match('/^\+?[0-9 ]{7,20}$/', $phone)) {
            throw new InvalidArgumentException('El teléfono del destinatario no es válido.');
        }
    }

    /** Primer nombre, para mostrar en el rastreo público sin exponer datos personales. */
    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
