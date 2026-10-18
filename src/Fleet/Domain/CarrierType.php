<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

enum CarrierType: string
{
    /** Conductor propio de la empresa. */
    case OwnDriver = 'own_driver';
    /** Transportadora externa (Servientrega, mensajería tercerizada...). */
    case ExternalCompany = 'external_company';
}
