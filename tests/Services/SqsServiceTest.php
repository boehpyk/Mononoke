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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSetAttributesSuccessReturnsEmptyResult(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->with('setQueueAttributes', [[
                'QueueUrl' => 'https://example.com/queue',
                'Attributes' => ['Policy' => '{"foo":"bar"}']
            ]])
            ->willReturn(new Result([])); // ✅ Empty response (success)

        $service = new SqsService($this->mockSqsClient);
        $service->setAttributes('https://example.com/queue', ['Policy' => '{"foo":"bar"}']);

        $this->assertTrue(true); // No exception means success
    }

    #[DataProvider('awsErrorProvider')]
    public function testSetAttributesHandlesKnownAwsErrorCodes(string $errorCode, string $expectedMessage): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->willThrowException($this->createAwsException($errorCode));

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $service = new SqsService($this->mockSqsClient);
        $service->setAttributes('https://example.com/queue', ['Policy' => '{"foo":"bar"}']);
    }

    public static function awsErrorProvider(): array
    {
        return [
            ['InvalidAddress', 'The specified queue ID is invalid.'],
            ['InvalidAttributeName', 'The specified attribute does not exist.'],
            ['InvalidAttributeValue', 'A queue attribute value is invalid.'],
            ['InvalidSecurity', 'The request was not made over HTTPS or did not use SigV4.'],
            ['OverLimit', 'This action violates a limit (e.g., too many permissions or inflight messages).'],
            ['QueueDoesNotExist', 'The queue does not exist or the QueueUrl is incorrect.'],
            ['RequestThrottled', 'Request was throttled - too many requests.'],
            ['UnsupportedOperation', 'Unsupported operation attempted on the queue.'],
        ];
    }

    public function testSetAttributesHandlesUnknownAwsErrorCode(): void
    {
        $this->mockSqsClient->expects($this->once())
            ->method('__call')
            ->willThrowException($this->createAwsException('SomethingWeird'));

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessage('Failed to set queue attributes');

        $service = new SqsService($this->mockSqsClient);
        $service->setAttributes('https://example.com/queue', ['Policy' => '{"foo":"bar"}']);
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

    private function createAwsException(string $awsErrorCode): AwsException
    {
        // Create a dummy CommandInterface mock needed by AwsException constructor
        $commandMock = $this->createMock(\Aws\CommandInterface::class);

        // Create real AwsException with message and dummy command
        $exception = new AwsException('Simulated error', $commandMock);

        // Now create a partial mock of AwsException that only overrides getAwsErrorCode()
        $mock = $this->getMockBuilder(AwsException::class)
            ->setConstructorArgs(['Simulated error', $commandMock])
            ->onlyMethods(['getAwsErrorCode'])
            ->getMock();

        // Override getAwsErrorCode to return our desired AWS error code
        $mock->method('getAwsErrorCode')->willReturn($awsErrorCode);

        return $mock;
    }
}
