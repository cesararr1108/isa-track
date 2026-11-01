<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class RouteStopModel extends Model
{
    public $timestamps = false;

    protected $table = 'delivery_route_stops';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'weight_kg' => 'float',
        'packages' => 'integer',
        'sequence' => 'integer',
    ];
}
