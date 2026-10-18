<?php

declare(strict_types=1);

namespace Isa\Shared\Domain;

/**
 * Error de regla de negocio. La capa HTTP lo traduce a una respuesta 422.
 */
abstract class DomainException extends \DomainException
{
    /** Código estable que el frontend puede usar para traducir el error. */
    abstract public function errorCode(): string;
}
