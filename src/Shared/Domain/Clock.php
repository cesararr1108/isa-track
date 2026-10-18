<?php

declare(strict_types=1);

namespace Isa\Shared\Domain;

use DateTimeImmutable;

/** Puerto para obtener la hora actual; permite fijar el tiempo en las pruebas. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
