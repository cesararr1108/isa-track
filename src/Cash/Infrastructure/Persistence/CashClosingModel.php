<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class CashClosingModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'cash_closings';

    protected $guarded = [];

    protected $casts = [
        'expected_by_method' => 'array',
        'orders_count' => 'integer',
        'cash_received' => 'integer',
        'difference' => 'integer',
        'closed_at' => 'immutable_datetime',
    ];
}
