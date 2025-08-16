<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Service as MononokeService;
use Kekke\Mononoke\Transport\AwsSns;

class Service extends MononokeService
{
    /**
     * Receive a message and forward to another topic using Mononoke\Transport\AwsSns
     */
    #[AwsSnsSqs('mononoke-topic', 'mononoke-queue')]
    public function incoming($message)
    {
        Logger::info("Received message!", ["Message" => $message]);
        AwsSns::publish(topic: 'another-topic', data: ['msg' => 'I have received a message and I\'m passing it along']);
    }

    #[AwsSnsSqs('mononoke-another-topic', 'mononoke-another-queue')]
    public function anotherIncoming($message)
    {
        Logger::info("Received message in another-topic!", ["Message" => $message]);
    }

    #[Http(HttpMethod::GET, '/health')]
    public function status()
    {
        return "OK";
    }
}
