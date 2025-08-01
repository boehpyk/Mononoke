<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Services;

use Aws\Sns\SnsClient;
use Kekke\Mononoke\Models\AwsCredentials;
use Aws\Exception\AwsException;
use Kekke\Mononoke\Exceptions\MononokeException;

class SnsService
{
    private SnsClient $sns;

    public function __construct(?SnsClient $snsClient = null)
    {
        if ($snsClient !== null) {
            $this->sns = $snsClient;
            return;
        }

        $creds = AwsCredentials::load();

        try {
            $this->sns = new SnsClient([
                'region' => $creds->region,
                'version' => 'latest',
                'endpoint' => $creds->endpoint,
                'credentials' => [
                    'key' => $creds->key,
                    'secret' => $creds->secret,
                ]
            ]);
        } catch (AwsException $e) {
            throw new MononokeException("AWS SDK failed to initialize: " . $e->getMessage());
        }
    }

    public function create(string $topicName): string
    {
        try {
            $result = $this->sns->createTopic(['Name' => $topicName]);
            return $result['TopicArn'];
        } catch (AwsException $e) {
            throw new MononokeException("Failed to create SNS topic '{$topicName}': " . $e->getAwsErrorMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new MononokeException("Unexpected error while creating SNS topic '{$topicName}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function notify(string $topicArn, string|array $message): void
    {
        try {
            if (is_array($message)) {
                $message = json_encode($message, JSON_THROW_ON_ERROR);
            }

            $this->sns->publish([
                'TopicArn' => $topicArn,
                'Message' => $message,
            ]);
        } catch (\JsonException $e) {
            throw new MononokeException("Failed to encode message to JSON: " . $e->getMessage(), $e->getCode(), $e);
        } catch (AwsException $e) {
            throw new MononokeException("Failed to publish message to SNS: " . $e->getAwsErrorMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new MononokeException("Unexpected error while publishing message: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function subscribe(string $topicArn, string $queueArn): void
    {
        try {
            $this->sns->subscribe([
                'Protocol' => 'sqs',
                'TopicArn' => $topicArn,
                'Endpoint' => $queueArn,
                'ReturnSubscriptionArn' => true
            ]);
        } catch (AwsException $e) {
            throw new MononokeException("Failed to setup SQS or subscription: " . $e->getAwsErrorMessage());
        }
    }
}
