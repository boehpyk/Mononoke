<?php

declare(strict_types=1);

namespace Tests\Scheduling;

use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Scheduling\FrozenClock;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\SchedulerEvaluator;
use PHPUnit\Framework\TestCase;

final class SchedulerEvaluatorTest extends TestCase
{
    private FrozenClock $clock;
    private SchedulerEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2025-08-09T07:00:00+00:00'));
        $this->evaluator = new SchedulerEvaluator($this->clock);
    }

    public function testInvokeImmediatelyRunsOnFirstCall(): void
    {
        $schedule = new Schedule(Scheduler::Hourly, invokeImmediately: true);
        $state = new ScheduleState(null);

        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testNonInvokeImmediatelyWaits(): void
    {
        $schedule = new Schedule(Scheduler::Hourly, invokeImmediately: false);
        $state = new ScheduleState(null);

        self::assertFalse($this->evaluator->shouldRun($schedule, $state));
    }

    public function testHourlyRunsAfterOneHour(): void
    {
        $schedule = new Schedule(Scheduler::Hourly);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        $this->clock->advance('+59 minutes');
        self::assertFalse($this->evaluator->shouldRun($schedule, $state));

        $this->clock->advance('+1 minute');
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testHourlyAtRunsOnlyAtSpecificMinuteSecond(): void
    {
        $schedule = new Schedule(Scheduler::HourlyAt, invokeAtMinute: 5, invokeAtSecond: 10);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        // Wrong time
        $this->clock->advance('+1 hour');
        self::assertFalse($this->evaluator->shouldRun($schedule, $state));

        // Correct time
        $this->clock->set(new \DateTimeImmutable('2025-08-09T08:05:10+00:00'));
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testEveryMinuteRunsAfterOneMinute(): void
    {
        $schedule = new Schedule(Scheduler::EveryMinute);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        $this->clock->advance('+59 seconds');
        self::assertFalse($this->evaluator->shouldRun($schedule, $state));

        $this->clock->advance('+1 second');
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testEveryMinuteAtRunsOnlyAtSpecificSecond(): void
    {
        $schedule = new Schedule(Scheduler::EveryMinuteAt, invokeAtSecond: 30);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        // Wrong second
        $this->clock->advance('+1 minute');
        self::assertFalse($this->evaluator->shouldRun($schedule, $state));

        // Correct second
        $this->clock->set(new \DateTimeImmutable('2025-08-09T07:01:30+00:00'));
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testEverySecondRunsOneSecondLater(): void
    {
        $schedule = new Schedule(Scheduler::EverySecond);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        $this->clock->advance('+1 second');
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testDailyAtRunsOnlyAtSpecificHourMinuteSecond(): void
    {
        $schedule = new Schedule(Scheduler::DailyAt, invokeAtHour: 9, invokeAtMinute: 30, invokeAtSecond: 0);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        // Wrong time
        $this->clock->advance('+1 day');
        self::assertFalse($this->evaluator->shouldRun($schedule, $state));

        // Correct time
        $this->clock->set(new \DateTimeImmutable('2025-08-10T09:30:00+00:00'));
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testEveryMinuteRunsEvenIfHourAndMinuteIsSupplied(): void
    {
        $schedule = new Schedule(Scheduler::EveryMinuteAt, invokeAtHour: 9, invokeAtMinute: 30, invokeAtSecond: 10);
        $state = new ScheduleState($this->clock->now()->getTimestamp());

        // Correct time
        $this->clock->set(new \DateTimeImmutable('2025-08-09T07:01:10+00:00'));
        self::assertTrue($this->evaluator->shouldRun($schedule, $state));
    }

    public function testAtSchedulesDoNotRunImmediatelyWhenInvokeImmediatelyIsFalse(): void
    {
        $scheduleHourAt = new Schedule(
            Scheduler::HourlyAt,
            invokeAtMinute: 5,
            invokeAtSecond: 10,
            invokeImmediately: false
        );

        $scheduleMinuteAt = new Schedule(
            Scheduler::EveryMinuteAt,
            invokeAtSecond: 30,
            invokeImmediately: false
        );

        $stateHourAt = new ScheduleState(null);
        $stateMinuteAt = new ScheduleState(null);

        // Current time does NOT match scheduled time
        // => should NOT run immediately
        $this->clock->set(new \DateTimeImmutable('2025-08-09T07:04:44+00:00'));

        self::assertFalse(
            $this->evaluator->shouldRun($scheduleHourAt, $stateHourAt),
            'HourlyAt ran immediately when it should not have.'
        );

        self::assertFalse(
            $this->evaluator->shouldRun($scheduleMinuteAt, $stateMinuteAt),
            'EveryMinuteAt ran immediately when it should not have.'
        );
    }
}
