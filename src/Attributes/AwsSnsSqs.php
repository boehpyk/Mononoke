<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Aws\Exception\AwsException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Services\SnsService;
use Kekke\Mononoke\Services\SqsService;

/**
 * Attribute to configure AWS SNS and SQS integration.
 *
 * When applied to a method, this attribute ensures that the specified SNS topic
 * and SQS queue are created (if they do not already exist) and that the queue
 * is subscribed to the topic. If registered with Mononoke, an event loop will be
 * initialized via ReactPHP to continuously poll messages from the SQS queue.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AwsSnsSqs
{
    public string $topicName;
    public string $queueName;
    public string $queueUrl;
    public string $queueArn;

    public function __construct(string $topicName, string $queueName)
    {
        $this->topicName = $topicName;
        $this->queueName = $queueName;
    }

    /**
     * This method creates a SNS topic, SQS queue and subscribes the queue to the topic
     * If the topic or queue exists then none will be created
     */
    public function setup()
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
}
