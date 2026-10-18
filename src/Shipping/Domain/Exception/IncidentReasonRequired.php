<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class IncidentReasonRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('Una novedad debe indicar el motivo.');
    }

    public function errorCode(): string
    {
        return 'incident_reason_required';
    }
}
