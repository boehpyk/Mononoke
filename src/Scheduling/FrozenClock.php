<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Scheduling;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }

    public function set(DateTimeImmutable $time): void
    {
        $this->now = $time;
    }
}