<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class PaymentModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'order_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'collected_at' => 'immutable_datetime',
    ];
}
