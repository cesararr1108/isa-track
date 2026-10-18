<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/**
 * Estados del pedido. Qué cambios están permitidos NO se define aquí sino en
 * StatusTransitionPolicy (configurable en config/isa.php -> shipping.transitions).
 */
enum OrderStatus: string
{
    case Created = 'created';     // Creado
    case Prepared = 'prepared';   // Alistado
    case Assigned = 'assigned';   // Asignado
    case InRoute = 'in_route';    // En ruta
    case Delivered = 'delivered'; // Entregado
    case Incident = 'incident';   // Novedad
    case Returned = 'returned';   // Devuelto
}
