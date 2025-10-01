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
    private ?string $dlqUrl = null;

    public function __construct(private string $topicName, private string $queueName, private ?string $dlqName) {}

    /**
     * Sets up a SNS topic and a SQS queue
     */
    public function setup(SnsService $snsService = new SnsService(), SqsService $sqsService = new SqsService()): void
    {
        try {
            $topicArn = $snsService->create(topicName: $this->topicName);
        } catch (MononokeException $e) {
            throw new MononokeException($e->getMessage());
        }

        try {
            if (!is_null($this->dlqName)) {
                $this->dlqUrl = $sqsService->create(queueName: $this->dlqName);
            }
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

            if ($this->dlqUrl) {
                $dlqAttributes = $sqsService->getAttributes(queueUrl: $this->dlqUrl, attributeNames: $fetchAttributes);

                if (!isset($dlqAttributes['Attributes']['QueueArn'])) {
                    throw new MononokeException("Missing Queue Attributes for dead letter queue: {$this->dlqUrl}");
                }

                $redrivePolicy = json_encode([
                    'deadLetterTargetArn' => $dlqAttributes['Attributes']['QueueArn'],
                    'maxReceiveCount'     => 5,
                ]);

                $sqsService->setAttributes(
                    queueUrl: $this->queueUrl,
                    attributes: ['RedrivePolicy' => $redrivePolicy]
                );
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
