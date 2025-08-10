<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Scheduling;

use Kekke\Mononoke\Exceptions\MissingCallableException;

final class ScheduledInvoker
{
    private $callable; // @phpstan-ignore-line

    public function __construct(
        private readonly ScheduleState $state,
        private readonly Clock $clock
    ) {}

    public function setCallable(callable $fn): void
    {
        $this->callable = $fn;
    }

    public function invoke(): void
    {
        if (!is_callable($this->callable)) {
            throw new MissingCallableException("No callable set for scheduled invocation.");
        }

        ($this->callable)();
        $this->state->updateInvocationTime($this->clock->now()->getTimestamp());
    }
}
