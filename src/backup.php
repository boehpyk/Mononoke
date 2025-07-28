<?php

declare(strict_types=1);

namespace Kekke\Phpain;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Phpain\Attributes\AwsSnsSqs;
use ReflectionClass;

class PhpainService
{
    protected SqsClient $sqs;
    protected SnsClient $sns;

    public function run(bool $worker)
    {
        if (!$worker) {
            http_response_code(200);
            echo "OK, FROM HTTP!";
            return;
        }

        $reflector = new ReflectionClass($this);
        $queueMap = [];

        $region = getenv('AWS_REGION') ?: 'us-east-1';
        $endpoint = getenv('AWS_ENDPOINT') ?: 'http://localhost:4566';

        $this->sqs = new SqsClient([
            'region' => $region,
            'version' => 'latest',
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
            ]
        ]);

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(AwsSnsSqs::class) as $attr) {
                /** @var AwsSnsSqs $instance */
                $instance = $attr->newInstance();
                $instance->setup();
                $queueMap[$instance->queueName] = $method->getName();
            }
        }

        // Start polling
        echo "Polling for messages...\n";

        while (true) {
            foreach ($queueMap as $queueUrl => $methodName) {
                try {
                    echo "Polling $methodName\n";
                    $result = $this->sqs->receiveMessage([
                        'QueueUrl' => $queueUrl,
                        'MaxNumberOfMessages' => 5,
                        'WaitTimeSeconds' => 10
                    ]);

                    if (!empty($result['Messages'])) {
                        foreach ($result['Messages'] as $message) {
                            $body = json_decode($message['Body'], true);
                            $snsMessage = json_decode($body['Message'] ?? '', true) ?? $body['Message'] ?? '';

                            // Dispatch message to method
                            $this->{$methodName}($snsMessage);

                            // Delete message
                            $this->sqs->deleteMessage([
                                'QueueUrl' => $queueUrl,
                                'ReceiptHandle' => $message['ReceiptHandle']
                            ]);
                        }
                    }
                } catch (AwsException $e) {
                    echo "Error polling queue: " . $e->getAwsErrorMessage() . "\n";
                }
            }
        }
    }
}
