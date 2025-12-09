<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Aws\Exception\AwsException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Models\AwsConfig;
use Kekke\Mononoke\Services\SnsService;
use Kekke\Mononoke\Services\SqsService;

class SnsSqsInstaller
{
    private string $queueUrl;
    private ?string $dlqUrl = null;

    public function __construct(private string $topicName, private string $queueName, private ?string $dlqName) {}

    /**
     * Method to get the AWS_REGION
     * This is only used when not automatically creating resources in AWS.
     */
    private function getAwsRegion(): string
    {
        return getenv('AWS_REGION')
            ?: getenv('AWS_DEFAULT_REGION')
            ?: throw new MononokeException("AWS region not set in environment");
    }

    /**
     * Method to get the AWS_ACCOUNT_ID env variable
     * This is only used when not automatically creating resources in AWS.
     */
    private function getAwsAccountId(): string
    {
        $id = getenv('AWS_ACCOUNT_ID');

        if (!$id) {
            throw new MononokeException("AWS_ACCOUNT_ID must be set in environment when using autoCreate false");
        }

        return $id;
    }

    /**
     * Sets up a SNS topic and a SQS queue
     */
    public function setup(AwsConfig $config = new AwsConfig(), SnsService $snsService = new SnsService(), SqsService $sqsService = new SqsService()): void
    {
        /**
         * If we are not creating sns topic, sqs queue and subscribing queue to topic, then just build the queueUrl 
         * so that we can poll from it.
         */
        if (!$config->autoCreateResources) {
            $region    = $this->getAwsRegion();
            $accountId = $this->getAwsAccountId();

            if (!is_null($this->dlqName)) {
                $this->dlqUrl = sprintf(
                    'https://sqs.%s.amazonaws.com/%s/%s',
                    $region,
                    $accountId,
                    $this->dlqName
                );
            }

            $this->queueUrl = sprintf(
                'https://sqs.%s.amazonaws.com/%s/%s',
                $region,
                $accountId,
                $this->queueName
            );

            return;
        }

        /**
         * This is creating a SNS topic, a SQS queue, optionally a DLQ queue, and then subscribing 
         * the SQS queue to the SNS topic
         */
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
                    'maxReceiveCount'     => $config->dlqMaxRetryCount,
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
