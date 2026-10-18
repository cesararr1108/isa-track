<?php

declare(strict_types=1);

namespace Isa\Shared\Domain;

/**
 * Recurso inexistente (o de otra empresa). La capa HTTP lo traduce a 404.
 */
abstract class NotFoundException extends DomainException {}
