<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Scheduling;

use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\Scheduler;
use DateTimeImmutable;

final class SchedulerEvaluator
{
    public function __construct(
        private readonly Clock $clock
    ) {}

    public function shouldRun(Schedule $schedule, ScheduleState $state): bool
    {
        $now = $this->clock->now();
        $prev = $state->getPreviousInvocation();

        if ($prev === null) {
            if ($schedule->invokeImmediately) {
                return true;
            }

            if (in_array($schedule->scheduler, [
                Scheduler::DailyAt,
                Scheduler::HourlyAt,
                Scheduler::EveryMinuteAt
            ], true)) {
                return $this->timeMatchesNow($now, $schedule);
            }

            return false;
        }

        // We have a previous invocation timestamp
        return match ($schedule->scheduler) {
            Scheduler::Daily =>
            $this->hasIntervalPassed($prev, '+1 day', $now),

            Scheduler::DailyAt =>
            $this->hasIntervalPassed($prev, '+1 day', $now) && $this->timeMatchesNow($now, $schedule),

            Scheduler::Hourly =>
            $this->hasIntervalPassed($prev, '+1 hour', $now),

            Scheduler::HourlyAt =>
            $this->hasIntervalPassed($prev, '+1 hour', $now) && $this->timeMatchesNow($now, $schedule),

            Scheduler::EveryMinute =>
            $this->hasIntervalPassed($prev, '+1 minute', $now),

            Scheduler::EveryMinuteAt =>
            $this->hasIntervalPassed($prev, '+1 minute', $now) && $this->timeMatchesNow($now, $schedule),

            Scheduler::EverySecond => ($prev + 1) <= $now->getTimestamp(),
        };
    }

    private function hasIntervalPassed(int $prev, string $interval, DateTimeImmutable $now): bool
    {
        return (new DateTimeImmutable("@$prev"))
            ->modify($interval)
            ->getTimestamp() <= $now->getTimestamp();
    }

    private function timeMatchesNow(DateTimeImmutable $now, Schedule $schedule): bool
    {
        if ($schedule->invokeAtSecond !== null && $schedule->invokeAtSecond !== (int)$now->format('s')) {
            return false;
        }

        if (
            $schedule->invokeAtMinute !== null
            && $schedule->invokeAtMinute !== (int)$now->format('i')
            && in_array($schedule->scheduler, [Scheduler::DailyAt, Scheduler::HourlyAt])
        ) {
            return false;
        }

        if (
            $schedule->invokeAtHour !== null
            && $schedule->invokeAtHour !== (int)$now->format('H')
            && in_array($schedule->scheduler, [Scheduler::DailyAt])
        ) {
            return false;
        }

        return true;
    }
}
