<?php

declare(strict_types=1);

namespace Isa\Shared\Domain;

/**
 * Base de los agregados: acumula los eventos de dominio ocurridos
 * para que la capa de aplicación los publique después de guardar.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return list<DomainEvent> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
