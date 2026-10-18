<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Isa\Shared\Infrastructure\Tenancy\BelongsToCompany;

final class CarrierModel extends Model
{
    use BelongsToCompany, HasUuids;

    protected $table = 'carriers';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}
