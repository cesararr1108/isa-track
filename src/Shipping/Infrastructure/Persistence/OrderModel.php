<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class OrderModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'promised_at' => 'immutable_datetime',
        'delivered_at' => 'immutable_datetime',
        'cash_on_delivery' => 'integer',
        'delivery_latitude' => 'float',
        'delivery_longitude' => 'float',
        'created_at' => 'immutable_datetime',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(PackageModel::class, 'order_id')->orderBy('code');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'order_id')->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(StatusChangeModel::class, 'order_id')->orderBy('occurred_at')->orderBy('id');
    }
}
