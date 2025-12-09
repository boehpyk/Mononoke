<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Tests\Aws;

use Aws\Result;
use Kekke\Mononoke\Aws\SnsSqsInstaller;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Models\AwsConfig;
use Kekke\Mononoke\Services\SnsService;
use Kekke\Mononoke\Services\SqsService;
use PHPUnit\Framework\TestCase;

class SnsSqsInstallerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('AWS_REGION');
        putenv('AWS_ACCOUNT_ID');

        parent::tearDown();
    }

    public function testSetupCreatesTopicAndQueueWithoutDlq(): void
    {
        // Arrange
        $snsMock = $this->createMock(SnsService::class);
        $sqsMock = $this->createMock(SqsService::class);

        $snsMock->expects($this->once())
            ->method('create')
            ->with($this->equalTo('test-topic'))
            ->willReturn('arn:aws:sns:us-east-1:123456789012:test-topic');

        $sqsMock->expects($this->once())
            ->method('create')
            ->with('test-queue')
            ->willReturn('https://sqs.us-east-1.amazonaws.com/123456789012/test-queue');

        $sqsMock->expects($this->once())
            ->method('getAttributes')
            ->with(
                'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue',
                ['QueueArn']
            )
            ->willReturn(new Result([
                'Attributes' => [
                    'QueueArn' => 'arn:aws:sqs:us-east-1:123456789012:test-queue'
                ]
            ]));

        $sqsMock->expects($this->once())
            ->method('allowSnsToSendMessagesToQueue')
            ->with(
                $this->anything(),
                'arn:aws:sqs:us-east-1:123456789012:test-queue',
                'arn:aws:sns:us-east-1:123456789012:test-topic'
            );

        $snsMock->expects($this->once())
            ->method('subscribe')
            ->with(
                'arn:aws:sns:us-east-1:123456789012:test-topic',
                'arn:aws:sqs:us-east-1:123456789012:test-queue'
            );

        // Act
        $installer = new SnsSqsInstaller('test-topic', 'test-queue', null);
        $installer->setup(new AwsConfig(), $snsMock, $sqsMock);

        // Assert
        $this->assertSame(
            'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue',
            $installer->getQueueUrl()
        );
    }

    public function testSetupDoesNotCreateTopicOrQueueWhenAutoCreateIsFalse(): void
    {
        // Arrange
        putenv('AWS_REGION=us-east-1');
        putenv('AWS_ACCOUNT_ID=123456789012');

        $snsMock = $this->createMock(SnsService::class);
        $sqsMock = $this->createMock(SqsService::class);

        $snsMock->expects($this->never())
            ->method('create');

        $sqsMock->expects($this->never())
            ->method('create');

        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        // Act
        $installer = new SnsSqsInstaller(
            topicName: 'test-topic',
            queueName: 'test-queue',
            dlqName: null,
        );

        $installer->setup(new AwsConfig(autoCreateResources: false), $snsMock, $sqsMock);

        // Assert
        $this->assertSame($queueUrl, $installer->getQueueUrl());
    }

    public function testSetupCreatesTopicAndQueueWithDlq(): void
    {
        // Arrange
        $defaultConfig = new AwsConfig();
        $snsMock = $this->createMock(SnsService::class);
        $sqsMock = $this->createMock(SqsService::class);

        $snsMock->method('create')
            ->willReturn('arn:aws:sns:us-east-1:123456789012:test-topic');

        $sqsMock->method('create')
            ->willReturnCallback(function (string $queueName) {
                if ($queueName === 'test-dlq') {
                    return 'https://sqs.us-east-1.amazonaws.com/123456789012/test-dlq';
                }
                if ($queueName === 'test-queue') {
                    return 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';
                }
                $this->fail("Unexpected queueName: {$queueName}");
            });

        $sqsMock->method('getAttributes')
            ->willReturnCallback(function (string $queueUrl, array $attrs) {
                if ($queueUrl === 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue') {
                    return new Result([
                        'Attributes' => [
                            'QueueArn' => 'arn:aws:sqs:us-east-1:123456789012:test-queue'
                        ]
                    ]);
                }
                if ($queueUrl === 'https://sqs.us-east-1.amazonaws.com/123456789012/test-dlq') {
                    return new Result([
                        'Attributes' => [
                            'QueueArn' => 'arn:aws:sqs:us-east-1:123456789012:test-dlq'
                        ]
                    ]);
                }
                $this->fail("Unexpected queueUrl: {$queueUrl}");
            });

        $sqsMock->expects($this->once())
            ->method('setAttributes')
            ->with(
                'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue',
                $this->callback(function ($attributes) use ($defaultConfig) {
                    $policy = json_decode($attributes['RedrivePolicy'], true);
                    return $policy['deadLetterTargetArn'] === 'arn:aws:sqs:us-east-1:123456789012:test-dlq'
                        && $policy['maxReceiveCount'] === $defaultConfig->dlqMaxRetryCount;
                })
            );

        // Act
        $installer = new SnsSqsInstaller('test-topic', 'test-queue', 'test-dlq');
        $installer->setup(new AwsConfig(), $snsMock, $sqsMock);
    }

    public function testThrowsIfQueueArnMissing(): void
    {
        // Arrange
        $this->expectException(MononokeException::class);
        $this->expectExceptionMessage('Missing Queue Attributes for queue');

        $snsMock = $this->createMock(SnsService::class);
        $sqsMock = $this->createMock(SqsService::class);

        $snsMock->method('create')->willReturn('arn:aws:sns:us-east-1:123456789012:test-topic');
        $sqsMock->method('create')->willReturn('https://sqs.us-east-1.amazonaws.com/123456789012/test-queue');
        $sqsMock->method('getAttributes')->willReturn(new Result(['Attributes' => []]));

        // Act
        $installer = new SnsSqsInstaller('test-topic', 'test-queue', null);
        $installer->setup(new AwsConfig(), $snsMock, $sqsMock);
    }
}
