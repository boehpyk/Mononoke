<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Services;

use Kekke\Mononoke\Models\AwsCredentials;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Exceptions\MononokeInvalidAttributesException;

/**
 * SQS Service
 */
class SqsService
{
    private SqsClient $sqs;

    /**
     * Creates a SqsClient based on aws credentials in env variables
     */
    public function __construct(?SqsClient $sqsClient = null)
    {
        if ($sqsClient !== null) {
            $this->sqs = $sqsClient;
            return;
        }

        $creds = AwsCredentials::load();

        try {
            $this->sqs = new SqsClient([
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

    /**
     * @param array<string> $attributeNames
     */
    private function validateAttributeNames(array &$attributeNames, bool $skipInvalidAttributeNames): void
    {
        $listOfInvalidAttributes = [];

        $validAttributeNames = [
            'All',
            'Policy',
            'VisibilityTimeout',
            'MaximumMessageSize',
            'MessageRetentionPeriod',
            'ApproximateNumberOfMessages',
            'ApproximateNumberOfMessagesNotVisible',
            'CreatedTimestamp',
            'LastModifiedTimestamp',
            'QueueArn',
            'ApproximateNumberOfMessagesDelayed',
            'DelaySeconds',
            'ReceiveMessageWaitTimeSeconds',
            'RedrivePolicy',
            'FifoQueue',
            'ContentBasedDeduplication',
            'KmsMasterKeyId',
            'KmsDataKeyReusePeriodSeconds',
            'DeduplicationScope',
            'FifoThroughputLimit',
            'RedriveAllowPolicy',
            'SqsManagedSseEnabled',
        ];

        foreach ($attributeNames as $name) {
            if (! in_array($name, $validAttributeNames, true)) {
                $listOfInvalidAttributes[] = $name;
            }
        }

        if (! empty($listOfInvalidAttributes) && ! $skipInvalidAttributeNames) {
            throw new MononokeInvalidAttributesException("Invalid attribute(s) provided: " . implode(" ", $listOfInvalidAttributes));
        }

        if (! empty($listOfInvalidAttributes) && $skipInvalidAttributeNames) {
            $attributeNames = array_values(array_diff($attributeNames, $listOfInvalidAttributes));
        }
    }

    /**
     * List attributes from a queue
     * @param array<string> $attributeNames
     * @return \Aws\Result<mixed>
     */
    public function getAttributes(string $queueUrl, array $attributeNames, bool $skipInvalidAttributeNames = true): \Aws\Result
    {

        $this->validateAttributeNames($attributeNames, $skipInvalidAttributeNames);

        $attrs = $this->sqs->getQueueAttributes([
            'QueueUrl' => $queueUrl,
            'AttributeNames' => $attributeNames,
        ]);

        return $attrs;
    }

    /**
     * Adds a policy to allow sns to send messages to the sqs queue
     */
    public function allowSnsToSendMessagesToQueue(string $queueUrl, string $queueArn, string $topicArn): void
    {
        $policy = [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'Allow-SNS-SendMessage',
                    'Effect' => 'Allow',
                    'Principal' => ['Service' => 'sns.amazonaws.com'],
                    'Action' => 'SQS:SendMessage',
                    'Resource' => $queueArn,
                    'Condition' => [
                        'ArnEquals' => [
                            'aws:SourceArn' => $topicArn,
                        ],
                    ],
                ],
            ],
        ];

        $this->setAttributes(queueUrl: $queueUrl, attributes: [
            'Policy' => json_encode($policy),
        ]);
    }

    /**
     * Sets a queues attributes
     * @param array<mixed> $attributes
     */
    public function setAttributes(string $queueUrl, array $attributes): void
    {
        try {
            $result = $this->sqs->setQueueAttributes([
                'QueueUrl' => $queueUrl,
                'Attributes' => $attributes,
            ]);
        } catch (AwsException $e) {
            $errorCode = $e->getAwsErrorCode();

            $knownErrors = [
                'InvalidAddress' => 'The specified queue ID is invalid.',
                'InvalidAttributeName' => 'The specified attribute does not exist.',
                'InvalidAttributeValue' => 'A queue attribute value is invalid.',
                'InvalidSecurity' => 'The request was not made over HTTPS or did not use SigV4.',
                'OverLimit' => 'This action violates a limit (e.g., too many permissions or inflight messages).',
                'QueueDoesNotExist' => 'The queue does not exist or the QueueUrl is incorrect.',
                'RequestThrottled' => 'Request was throttled - too many requests.',
                'UnsupportedOperation' => 'Unsupported operation attempted on the queue.',
            ];

            $message = $knownErrors[$errorCode]
                ?? "Failed to set queue attributes: {$e->getMessage()}";

            throw new MononokeException($message, $e->getCode(), $e);
        }
    }

    /**
     * Creates a queue
     */
    public function create(string $queueName): string
    {
        try {
            $queueResult = $this->sqs->createQueue([
                'QueueName' => $queueName,
            ]);

            return $queueResult['QueueUrl'];
        } catch (AwsException $e) {
            throw new MononokeException("Failed to create SQS queue '{$queueName}': " . $e->getAwsErrorMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new MononokeException("Unexpected error while creating SQS queue '{$queueName}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Returns the SqsClient
     */
    public function getClient(): SqsClient
    {
        return $this->sqs;
    }
}
