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
}
