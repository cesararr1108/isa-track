<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class ZoneModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'zones';

    protected $guarded = [];

    protected $casts = [
        'polygon' => 'array',
        'delivery_fee' => 'integer',
        'estimated_minutes' => 'integer',
        'active' => 'boolean',
    ];
}
