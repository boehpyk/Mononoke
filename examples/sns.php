<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\HttpGet;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[AwsSnsSqs('topic', 'queue')]
    public function incoming($message)
    {
        echo "📩 Received:\n";
        print_r($message);
    }

    #[HttpGet('/health')]
    public function status()
    {
        return "OK";
    }
}

$service = new Service();
$service->run();
