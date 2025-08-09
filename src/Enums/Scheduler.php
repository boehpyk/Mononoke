<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

use Kekke\Mononoke\Attributes\Schedule;

/**
 * Enum with schedule timings
 */
enum Scheduler
{
    case Daily;
    case DailyAt;
    case Hourly;
    case HourlyAt;
    case EveryMinute;
    case EveryMinuteAt;
    case EverySecond;

    public function requiresHour(): bool
    {
        return in_array($this, [Scheduler::DailyAt]);
    }

    public function requiresMinute(): bool
    {
        return in_array($this, [Scheduler::DailyAt, Scheduler::HourlyAt]);
    }

    public function requiresSecond(): bool
    {
        return in_array($this, [Scheduler::DailyAt, Scheduler::HourlyAt, Scheduler::EveryMinuteAt]);
    }
}
