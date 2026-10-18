<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Isa\Shared\Application\EventBus;
use Isa\Shared\Domain\DomainEvent;

/** Publica los eventos de dominio con el sistema de eventos de Laravel. */
final class LaravelEventBus implements EventBus
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
