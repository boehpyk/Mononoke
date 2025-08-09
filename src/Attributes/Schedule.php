<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Exceptions\InvalidScheduleConfigurationException;

/**
 * Attribute to define a scheduled action (metadata only).
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Schedule
{
    public function __construct(
        public readonly Scheduler $scheduler,
        public readonly ?int $invokeAtMinute = null,
        public readonly ?int $invokeAtHour = null,
        public readonly ?int $invokeAtSecond = null,
        public readonly bool $invokeImmediately = false
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->scheduler->requiresHour() && $this->invokeAtHour === null) {
            throw new InvalidScheduleConfigurationException("Scheduler {$this->scheduler->name} requires an hour.");
        }
        if ($this->scheduler->requiresMinute() && $this->invokeAtMinute === null) {
            throw new InvalidScheduleConfigurationException("Scheduler {$this->scheduler->name} requires a minute.");
        }
        if ($this->scheduler->requiresSecond() && $this->invokeAtSecond === null) {
            throw new InvalidScheduleConfigurationException("Scheduler {$this->scheduler->name} requires a second.");
        }
    }
}
