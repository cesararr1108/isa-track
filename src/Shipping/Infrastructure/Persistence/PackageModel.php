<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PackageModel extends Model
{
    use HasUuids;

    protected $table = 'order_packages';

    protected $guarded = [];

    protected $casts = ['weight_kg' => 'float'];
}
