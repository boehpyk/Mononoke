<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Transport;

use Aws\Sdk;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use JsonException;
use Kekke\Mononoke\Aws\AwsClientFactory;
use Kekke\Mononoke\Enums\ClientType;
use Kekke\Mononoke\Exceptions\MononokeException;
use Throwable;

/**
 * AwsSqs helper methods
 */
class AwsSqs
{
    private static ?SqsClient $client = null;

    /**
     * Set a SqsClient
     */
    public static function setSqsClient(SqsClient $client): void
    {
        self::$client = $client;
    }

    /**
     * Publish to a sqs queue
     * @param array<mixed> $data
     */
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

            /** @var \Aws\Result<mixed> $result */
            $result = $client->getQueueUrl(['QueueName' => $queueName]);
            $queueUrl = $result->get('QueueUrl');

            return $client->sendMessage([
                'QueueUrl'    => $queueUrl,
                'MessageBody' => $payload,
            ]);
        } catch (AwsException $e) {
            throw new MononokeException("AWS SQS publish failed: " . $e->getAwsErrorMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new MononokeException("Unexpected error during SQS publish: " . $e->getMessage(), 0, $e);
        }
    }

    private static function getClient(): SqsClient
    {
        if (self::$client === null) {
            /** @var SqsClient $client */
            $client = AwsClientFactory::create(ClientType::SNS);
            self::$client = $client;
        }

        return self::$client;
    }
}
