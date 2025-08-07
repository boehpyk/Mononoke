<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[Http('GET', '/health')]
    public function status()
    {
        return "OK";
    }

    #[Http('POST', '/restart')]
    public function restart()
    {
        return "Restarting";
    }
}
