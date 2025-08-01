<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Service as MononokeService;
use Kekke\Mononoke\Transport\AwsSns;

class Service extends MononokeService
{
    /**
     * Receive a message and forward to another topic using Mononoke\SnsPublish
     */
    #[AwsSnsSqs('topic', 'queue')]
    public function incoming($message)
    {
        echo "📩 Received:\n";
        print_r($message);
        AwsSns::publish(topic: 'another-topic', data: ['msg' => $message]);
    }

    #[AwsSnsSqs('another-topic', 'another-queue')]
    public function anotherIncoming($message)
    {
        echo "ANOTHER TOPIC!\n📩 Received:\n";
        print_r($message);
    }

    #[Http('GET', '/health')]
    public function status()
    {
        return "OK";
    }
}

$service = new Service();
$service->run();
