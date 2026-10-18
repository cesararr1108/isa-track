<?php

declare(strict_types=1);

namespace Isa\Shared\Application;

use Isa\Shared\Domain\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
