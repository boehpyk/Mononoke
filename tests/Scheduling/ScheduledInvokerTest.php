<?php

declare(strict_types=1);

namespace Tests\Scheduling;

use Kekke\Mononoke\Exceptions\MissingCallableException;
use Kekke\Mononoke\Scheduling\FrozenClock;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\ScheduledInvoker;
use PHPUnit\Framework\TestCase;

final class ScheduledInvokerTest extends TestCase
{
    public function testInvokeUpdatesState(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2025-08-09T07:00:00+00:00'));
        $state = new ScheduleState(null);
        $invoker = new ScheduledInvoker($state, $clock);

        $ran = false;
        $invoker->setCallable(function () use (&$ran) {
            $ran = true;
        });

        $invoker->invoke();

        self::assertTrue($ran);
        self::assertSame($clock->now()->getTimestamp(), $state->getPreviousInvocation());
    }

    public function testInvokeWithoutCallableThrows(): void
    {
        $this->expectException(MissingCallableException::class);

        $clock = new FrozenClock(new \DateTimeImmutable('2025-08-09T07:00:00+00:00'));
        $state = new ScheduleState(null);
        $invoker = new ScheduledInvoker($state, $clock);

        $invoker->invoke();
    }
}
