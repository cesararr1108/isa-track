<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

/** Vehículo con el que el mensajero hace las entregas. */
enum VehicleType: string
{
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case Van = 'van';
    case Truck = 'truck';
    case OnFoot = 'on_foot';
}
