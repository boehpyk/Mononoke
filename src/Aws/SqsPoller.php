<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Helpers\Logger;

class SqsPoller
{
    public function __construct(private SqsClient $client, private string $queueUrl) {}

    /**
     * Polls messages from sqs queue and returns the messages
     * returns an empty array if no messages are received
     * 
     * @return array<mixed>
     */
    public function poll(): array
    {
        try {
            /** @var \Aws\Result<mixed> $result */
            $result = $this->client->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'MaxNumberOfMessages' => 5,
                'WaitTimeSeconds' => 0
            ]);
        } catch (AwsException $e) {
            Logger::exception(message: "Error polling queue", exception: $e);
            return [];
        }

        if (!isset($result['Messages'])) {
            return [];
        }

        return $result['Messages'];
    }

    public function delete(string $receiptHandle): void
    {
        $this->client->deleteMessage([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $receiptHandle
        ]);
    }
}
