<?php

declare(strict_types=1);

namespace Isa\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent = detalle de infraestructura (la "fila" de la tabla).
 * El dominio nunca lo ve; el repositorio traduce entre ambos.
 */
final class CompanyModel extends Model
{
    use HasUuids;

    protected $table = 'companies';

    protected $guarded = [];
}
