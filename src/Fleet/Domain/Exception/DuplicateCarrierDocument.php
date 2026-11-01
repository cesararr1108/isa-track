<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DuplicateCarrierDocument extends DomainException
{
    public function __construct(string $document)
    {
        parent::__construct(sprintf('Ya existe un mensajero con la cédula %s.', $document));
    }

    public function errorCode(): string
    {
        return 'duplicate_carrier_document';
    }
}
