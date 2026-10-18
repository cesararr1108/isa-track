<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class StatusChangeModel extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'occurred_at' => 'immutable_datetime',
    ];
}
