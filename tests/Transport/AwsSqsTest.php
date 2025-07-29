<?php

declare(strict_types=1);

use Aws\Result;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Transport\AwsSqs;
use PHPUnit\Framework\TestCase;

use function React\Promise\resolve;
use function React\Promise\reject;

final class AwsSqsTest extends TestCase
{
    private SqsClient $mockClient;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(SqsClient::class);
        AwsSqs::setSqsClient($this->mockClient);
    }

    public function testSqsPublishSuccess(): void
    {
        $this->mockClient
            ->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'getQueueUrl' && $args[0] === ['QueueName' => 'test']) {
                    return new Result(['QueueUrl' => 'https://sqs.local/queue/test']);
                }

                if (
                    $method === 'sendMessage' &&
                    $args[0] === [
                        'QueueUrl' => 'https://sqs.local/queue/test',
                        'MessageBody' => '{"foo":"bar"}',
                    ]
                ) {
                    return new Result(['MessageId' => 'abc123']);
                }

                throw new \RuntimeException("Unexpected method call: $method");
            });

        $response = AwsSqs::publish('test', ['foo' => 'bar']);

        $this->assertInstanceOf(Result::class, $response);
        $this->assertEquals('abc123', $response->get('MessageId'));
    }

    public function testSqsPublishFailure(): void
    {
        $this->mockClient
            ->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if (
                    $method === 'sendMessage' &&
                    $args[0] === [
                        'QueueUrl' => 'https://sqs.local/queue/test',
                        'MessageBody' => '{"msg":"Error"}'
                    ]
                ) {
                    return reject(new \Exception('Publish failed'));
                }

                if (
                    $method === 'getQueueUrl' &&
                    $args[0] === ['QueueName' => 'test']
                ) {
                    return new Result(['QueueUrl' => 'https://sqs.local/queue/test']);
                }

                throw new \RuntimeException("Unexpected method call: $method");
            });

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessage('Unexpected error during SQS publish: Publish failed');

        AwsSqs::publish('test', ['msg' => 'Error']);
    }

    public function testSqsPublishThrowsOnInvalidJson(): void
    {
        // Create invalid JSON payload by using a non-UTF8 character
        $badData = ["\xB1\x31" => "bad"];

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessageMatches('/Failed to encode SQS message to JSON:/');

        AwsSqs::publish('arn:aws:sns:localstack:test-topic', $badData);
    }
}
