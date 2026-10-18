<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/** Motivos de novedad en la entrega. */
enum IncidentReason: string
{
    case WrongAddress = 'wrong_address';     // Dirección errada
    case CustomerAbsent = 'customer_absent'; // Cliente ausente
    case Rejected = 'rejected';              // Rechazado
    case Rescheduled = 'rescheduled';        // Reprogramado
    case Other = 'other';
}
