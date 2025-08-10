<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Aws\Exception\AwsException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Services\SnsService;
use Kekke\Mononoke\Services\SqsService;

class SnsSqsInstaller
{
    private string $queueUrl;

    public function __construct(private string $topicName, private string $queueName) {}

    /**
     * Sets up a SNS topic and a SQS queue
     */
    public function setup(): void
    {
        try {
            $snsService = new SnsService();
            $topicArn = $snsService->create(topicName: $this->topicName);
        } catch (MononokeException $e) {
            throw new MononokeException($e->getMessage());
        }

        try {
            $sqsService = new SqsService();
            $this->queueUrl = $sqsService->create(queueName: $this->queueName);
        } catch (MononokeException $e) {
            throw new MononokeException("Failed setting up SQS: {$e->getMessage()}");
        }

        try {
            $fetchAttributes = ['QueueArn'];
            $queueAttributes = $sqsService->getAttributes(queueUrl: $this->queueUrl, attributeNames: $fetchAttributes);

            if (!isset($queueAttributes['Attributes']['QueueArn'])) {
                throw new MononokeException("Missing Queue Attributes for queue: {$this->queueUrl}");
            }

            $sqsService->allowSnsToSendMessagesToQueue(
                queueUrl: $this->queueUrl,
                queueArn: $queueAttributes['Attributes']['QueueArn'],
                topicArn: $topicArn
            );
            $snsService->subscribe(topicArn: $topicArn, queueArn: $queueAttributes['Attributes']['QueueArn']);
        } catch (AwsException $e) {
            throw new MononokeException("Failed to setup subscription: " . $e->getMessage());
        }
    }

    /**
     * Returns the queueUrl
     */
    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }
}
