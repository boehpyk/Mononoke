<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Transport;

use Aws\Sdk;
use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use Exception;
use JsonException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Models\AwsCredentials;
use Throwable;

use function React\Async\async;
use function React\Async\await;

class AwsSns
{
    private static ?SnsClient $client = null;

    public static function setSnsClient(SnsClient $client): void
    {
        self::$client = $client;
    }

    public static function publish(string $topic, array $data): mixed
    {
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MononokeException("Failed to encode SNS message to JSON: " . $e->getMessage(), 0, $e);
        }

        try {
            if (!self::$client) {
                self::$client = self::getClient();
            }

            $client = self::$client;
            try {
                $topicResult = $client->createTopic(['Name' => $topic]);
                $topicArn = $topicResult['TopicArn'];
            } catch (AwsException $createEx) {
                throw new Exception("Insufficient permissions to create SNS topic '{$topic}': " . $createEx->getMessage());
            }

            return $client->publish([
                'TopicArn' => $topicArn,
                'Message' => $payload,
            ]);
        } catch (AwsException $e) {
            throw new MononokeException("AWS SNS publish failed: " . $e->getAwsErrorMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new MononokeException("Unexpected error during SNS publish: " . $e->getMessage(), 0, $e);
        }
    }

    private static function getClient(): SnsClient
    {
        if (self::$client === null) {
            $creds = AwsCredentials::load();

            $sdk = new Sdk([
                'region' => $creds->region,
                'version' => 'latest',
                'endpoint' => $creds->endpoint,
                'credentials' => [
                    'key' => $creds->key,
                    'secret' => $creds->secret,
                ],
            ]);

            self::$client = $sdk->createSns();
        }

        return self::$client;
    }
}
