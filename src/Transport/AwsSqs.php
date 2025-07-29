<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Transport;

use Aws\Sdk;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use JsonException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Models\AwsCredentials;
use Throwable;

use function React\Async\async;
use function React\Async\await;

class AwsSqs
{
    private static ?SqsClient $client = null;

    public static function setSqsClient(SqsClient $client): void
    {
        self::$client = $client;
    }

    public static function publish(string $queueName, array $data): mixed
    {
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MononokeException("Failed to encode SQS message to JSON: " . $e->getMessage(), 0, $e);
        }

        try {
            if (!self::$client) {
                self::$client = self::getClient();
            }

            $client = self::$client;

            $result = $client->getQueueUrl([
                'QueueName' => $queueName,
            ]);

            $queueUrl = $result->get('QueueUrl');

            return await(async(function () use ($client, $queueUrl, $payload) {
                return $client->sendMessage([
                    'QueueUrl'    => $queueUrl,
                    'MessageBody' => $payload,
                ]);
            })());
        } catch (AwsException $e) {
            throw new MononokeException("AWS SQS publish failed: " . $e->getAwsErrorMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new MononokeException("Unexpected error during SQS publish: " . $e->getMessage(), 0, $e);
        }
    }

    private static function getClient(): SqsClient
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

            self::$client = $sdk->createSqs();
        }

        return self::$client;
    }
}
