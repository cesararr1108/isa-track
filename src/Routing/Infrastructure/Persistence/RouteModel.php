<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class RouteModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'delivery_routes';

    protected $guarded = [];

    protected $casts = [
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'distance_km' => 'float',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStopModel::class, 'route_id')->orderBy('sequence');
    }
}
