<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Exception;

#[Attribute(Attribute::TARGET_METHOD)]
class AwsSnsSqs
{
    public string $topicName;
    public string $queueName;

    public function __construct(string $topicName, string $queueName)
    {
        $this->topicName = $topicName;
        $this->queueName = $queueName;
    }

    public function setup()
    {
        try {
            $region = getenv('AWS_REGION') ?: 'us-east-1';
            $endpoint = getenv('AWS_ENDPOINT') ?: 'http://localhost:4566';

            $sns = new SnsClient([
                'region' => $region,
                'version' => 'latest',
                'endpoint' => $endpoint,
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
                ]
            ]);

            $sqs = new SqsClient([
                'region' => $region,
                'version' => 'latest',
                'endpoint' => $endpoint,
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
                ]
            ]);
        } catch (AwsException $e) {
            throw new Exception("AWS SDK failed to initialize: " . $e->getMessage());
        }

        try {
            $topicResult = $sns->createTopic(['Name' => $this->topicName]);
            $topicArn = $topicResult['TopicArn'];
        } catch (AwsException $createEx) {
            throw new Exception("Insufficient permissions to create SNS topic '{$this->topicName}': " . $createEx->getMessage());
        }

        try {
            $queueResult = $sqs->createQueue([
                'QueueName' => $this->queueName,
            ]);

            $queueUrl = $queueResult['QueueUrl'];
        } catch (AwsException $createEx) {
            throw new Exception("Insufficient permissions to create SQS queue '{$this->queueName}': " . $createEx->getMessage());
        }

        try {
            $attrs = $sqs->getQueueAttributes([
                'QueueUrl' => $queueUrl,
                'AttributeNames' => ['QueueArn']
            ]);
            $queueArn = $attrs['Attributes']['QueueArn'];

            $policy = [
                "Version" => "2012-10-17",
                "Statement" => [[
                    "Sid" => "Allow-SNS-SendMessage",
                    "Effect" => "Allow",
                    "Principal" => ["Service" => "sns.amazonaws.com"],
                    "Action" => "sqs:SendMessage",
                    "Resource" => $queueArn,
                    "Condition" => [
                        "ArnEquals" => ["aws:SourceArn" => $topicArn]
                    ]
                ]]
            ];

            $sqs->setQueueAttributes([
                'QueueUrl' => $queueUrl,
                'Attributes' => [
                    'Policy' => json_encode($policy)
                ]
            ]);

            $sns->subscribe([
                'Protocol' => 'sqs',
                'TopicArn' => $topicArn,
                'Endpoint' => $queueArn,
                'ReturnSubscriptionArn' => true
            ]);

            echo "SNS topic and SQS queue configured successfully.\n";
        } catch (AwsException $e) {
            throw new Exception("Failed to setup SQS or subscription: " . $e->getAwsErrorMessage());
        }
    }
}
