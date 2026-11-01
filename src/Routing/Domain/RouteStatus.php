<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

enum RouteStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Planned => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Planned || $this === self::InProgress;
    }
}
