<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

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
}