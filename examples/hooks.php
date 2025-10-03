<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Attributes\Hook;
use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Enums\RuntimeEvent;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Models\AwsConfig;
use Kekke\Mononoke\Models\HttpConfig;
use Kekke\Mononoke\Models\MononokeConfig;
use Kekke\Mononoke\Service as MononokeService;


#[Config(
    mononoke: new MononokeConfig(numberOfTaskWorkers: 5),
    aws: new AwsConfig(sqsPollTimeInSeconds: 10),
    http: new HttpConfig(port: 8080),
)]
class Service extends MononokeService
{
    #[Hook(event: RuntimeEvent::OnStart)]
    public function onStart()
    {
        Logger::info("Service started");
    }

    #[Hook(event: RuntimeEvent::OnShutdown)]
    public function onShutdown()
    {
        Logger::info("Service shutdown");
    }

    #[Schedule(Scheduler::EverySecond, invokeImmediately: true)]
    public function schedule(): void
    {
        Logger::info("--- Scheduler");
    }
}
