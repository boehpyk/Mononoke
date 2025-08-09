<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[Schedule(Scheduler::Daily)]
    public function daily()
    {
        Logger::info("Ran daily");
    }

    #[Schedule(Scheduler::Hourly)]
    public function hour()
    {
        Logger::info("Ran hourly");
    }

    #[Schedule(Scheduler::HourlyAt, invokeAtMinute: 12, invokeAtSecond: 10)]
    public function hourAt()
    {
        Logger::info("Ran hourAt 55:00");
    }

    #[Schedule(Scheduler::EveryMinuteAt, invokeAtSecond: 30)]
    public function minuteAt()
    {
        Logger::info("Ran minuteAt 30");
    }

    #[Schedule(Scheduler::EveryMinute, invokeImmediately: true)]
    public function minute()
    {
        Logger::info("Ran minute");
    }

    #[Schedule(Scheduler::EverySecond, invokeImmediately: true)]
    public function second()
    {
        Logger::info("Ran second");
    }
}
