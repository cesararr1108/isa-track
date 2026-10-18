<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Laravel;

use DateTimeImmutable;
use DateTimeZone;
use Isa\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function __construct(private readonly string $timezone) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($this->timezone));
    }
}
