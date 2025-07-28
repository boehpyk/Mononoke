<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\HttpGet;
use Kekke\Mononoke\Attributes\HttpPost;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[HttpGet('/health')]
    public function status()
    {
        return "OK";
    }

    #[HttpPost('/restart')]
    public function restart()
    {
        return "Restarting";
    }
}

$service = new Service();
$service->run();
