<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Laravel;

use Illuminate\Database\ConnectionInterface;
use Isa\Shared\Application\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction($operation);
    }
}
