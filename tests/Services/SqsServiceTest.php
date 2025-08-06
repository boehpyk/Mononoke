<?php

declare(strict_types=1);

namespace Tests\Services;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Exceptions\MononokeInvalidAttributesException;
use Kekke\Mononoke\Services\SqsService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class SqsServiceTest extends TestCase
{
    private SqsClient|\PHPUnit\Framework\MockObject\MockObject $mockSqsClient;

    protected function setUp(): void
    {
        $this->mockSqsClient = $this->createMock(SqsClient::class);
    }

    public function testCreateQueueSuccess(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('createQueue', [['QueueName' => 'test-queue']])
            ->willReturn(new Result(['QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue']));

        $service = new SqsService($this->mockSqsClient);
        $url = $service->create('test-queue');

        $this->assertSame('https://sqs.us-east-1.amazonaws.com/123456789012/test-queue', $url);
    }

    public function testCreateQueueThrowsMononokeException(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('createQueue')
            ->willThrowException(new AwsException('Error', $this->createMock(CommandInterface::class)));

        $this->expectException(MononokeException::class);

        $service = new SqsService($this->mockSqsClient);
        $service->create('test-queue');
    }

    public function testGetAttributesSuccess(): void
    {
        $expectedResult = new Result(['Attributes' => ['QueueArn' => 'arn:aws:sqs:us-east-1:123456789012:test']]);

        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('getQueueAttributes', [[
                'QueueUrl' => 'https://example.com/queue',
                'AttributeNames' => ['QueueArn']
            ]])
            ->willReturn($expectedResult);

        $service = new SqsService($this->mockSqsClient);

        $result = $service->getAttributes('https://example.com/queue', ['QueueArn']);
        $this->assertSame($expectedResult, $result);
    }

    public function testGetAttributesWithInvalidAttributeThrows(): void
    {
        $this->expectException(MononokeInvalidAttributesException::class);

        $service = new SqsService($this->mockSqsClient);
        $service->getAttributes('https://example.com/queue', ['InvalidAttribute'], false);
    }

    public function testGetAttributesSkipsInvalidAttributes(): void
    {
        $expectedResult = new Result(['Attributes' => []]);

        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('getQueueAttributes', [[
                'QueueUrl' => 'https://example.com/queue',
                'AttributeNames' => []
            ]])
            ->willReturn($expectedResult);

        $service = new SqsService($this->mockSqsClient);
        $result = $service->getAttributes('https://example.com/queue', ['InvalidAttribute'], true);

        $this->assertSame($expectedResult, $result);
    }

    public function testSetAttributesSuccess(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('setQueueAttributes', [[
                'QueueUrl' => 'https://example.com/queue',
                'Attributes' => ['Policy' => '{"foo":"bar"}']
            ]])
            ->willReturn(new Result());

        $service = new SqsService($this->mockSqsClient);
        $service->setAttributes('https://example.com/queue', ['Policy' => '{"foo":"bar"}']);
        $this->assertTrue(true); // If no exception, it's successful
    }

    public function testSetAttributesThrowsMononokeException(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('setQueueAttributes')
            ->willThrowException(new AwsException('Denied', $this->createMock(CommandInterface::class)));

        $this->expectException(MononokeException::class);

        $service = new SqsService($this->mockSqsClient);
        $service->setAttributes('https://example.com/queue', ['Policy' => 'x']);
    }

    public function testAllowSnsToSendMessagesToQueue(): void
    {
        $mock = $this->mockSqsClient;

        $mock->expects($this->once())
            ->method('__call')
            ->with('setQueueAttributes', $this->callback(function ($arg) {
                $this->assertSame('https://example.com/queue', $arg[0]['QueueUrl']);
                $policy = json_decode($arg[0]['Attributes']['Policy'], true);
                $this->assertSame('Allow-SNS-SendMessage', $policy['Statement'][0]['Sid']);
                return true;
            }))
            ->willReturn(new Result());

        $service = new SqsService($mock);
        $service->allowSnsToSendMessagesToQueue(
            'https://example.com/queue',
            'arn:aws:sqs:us-east-1:123456789012:test',
            'arn:aws:sns:us-east-1:123456789012:topic'
        );
    }
}
