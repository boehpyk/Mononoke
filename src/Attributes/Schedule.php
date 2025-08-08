<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\Scheduler;

/**
 * Attribute to define a scheduled action.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Schedule
{
    /** @var callable */
    private $callable;
    private int $invokeAtMinute;
    private int $invokeAtHour;
    private int $invokeAtSecond;

    private Scheduler $scheduler;
    private ?int $previousInvocation = null;

    public function __construct(
        Scheduler $scheduler,
        ?int $invokeAtMinute = null,
        ?int $invokeAtHour = null,
        ?int $invokeAtSecond = null,
        ?bool $invokeImmediately = false
    ) {
        $this->scheduler = $scheduler;

        if (
            !$invokeImmediately &&
            in_array($this->scheduler, [Scheduler::Daily, Scheduler::Hourly, Scheduler::EveryMinute, Scheduler::EverySecond])
        ) {
            $this->previousInvocation = time();
        }

        if (!is_null($invokeAtHour)) {
            $this->invokeAtHour = $invokeAtHour;
        }

        if (!is_null($invokeAtMinute)) {
            $this->invokeAtMinute = $invokeAtMinute;
        }

        if (!is_null($invokeAtSecond)) {
            $this->invokeAtSecond = $invokeAtSecond;
        }
    }

    public function shouldRun(): bool
    {
        if (
            is_null($this->previousInvocation) &&
            in_array($this->scheduler, [Scheduler::Daily, Scheduler::Hourly, Scheduler::EveryMinute, Scheduler::EverySecond])
        ) {
            return true;
        }

        if ($this->scheduler == Scheduler::Daily && strtotime('+1 day', $this->previousInvocation) < time()) {
            return true;
        }

        if (
            $this->scheduler === Scheduler::DailyAt &&
            (strtotime('+1 day', $this->previousInvocation) <= time() || is_null($this->previousInvocation)) &&
            $this->invokeAtSecond === (int)date("s") &&
            $this->invokeAtMinute === (int)date("i") &&
            $this->invokeAtHour === (int)date("H")
        ) {
            return true;
        }

        if ($this->scheduler == Scheduler::Hourly && strtotime('+1 hour', $this->previousInvocation) < time()) {
            return true;
        }

        if (
            $this->scheduler === Scheduler::HourlyAt &&
            (strtotime('+1 hour', $this->previousInvocation) <= time() || is_null($this->previousInvocation)) &&
            $this->invokeAtSecond === (int)date("s") &&
            $this->invokeAtMinute === (int)date("i")
        ) {
            return true;
        }

        if ($this->scheduler == Scheduler::EveryMinute && strtotime('+1 minute', $this->previousInvocation) < time()) {
            return true;
        }

        if (
            $this->scheduler === Scheduler::EveryMinuteAt &&
            (strtotime('+1 minute', $this->previousInvocation) <= time() || is_null($this->previousInvocation)) &&
            $this->invokeAtSecond === (int)date("s")
        ) {
            return true;
        }

        if ($this->scheduler == Scheduler::EverySecond && $this->previousInvocation + 1 <= time()) {
            return true;
        }

        return false;
    }

    public function invoke(): void
    {
        ($this->callable)();

        $this->previousInvocation = time();
    }

    public function setInvokeMethod(callable $fn): void
    {
        $this->callable = $fn;
    }
}
