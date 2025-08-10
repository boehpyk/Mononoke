<?php

use PHPUnit\Framework\TestCase;
use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use Kekke\Mononoke\Services\SnsService;
use Kekke\Mononoke\Exceptions\MononokeException;

class SnsServiceTest extends TestCase
{
    private SnsClient|\PHPUnit\Framework\MockObject\MockObject $mockSnsClient;
    private SnsService $service;

    protected function setUp(): void
    {
        $mockClient = $this->getMockBuilder(SnsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call']) // Important: mock the magic method
            ->getMock();
        $this->mockSnsClient = $mockClient;
        $this->service = new SnsService($this->mockSnsClient);
    }

    public function testCreateTopic(): void
    {
        $this->mockSnsClient->expects($this->once())
            ->method('__call')
            ->with('createTopic', [['Name' => 'my-topic']])
            ->willReturn(['TopicArn' => 'arn:aws:sns:us-east-1:123456789012:my-topic']);

        $service = new SnsService($this->mockSnsClient);

        $arn = $service->create('my-topic');
        $this->assertSame('arn:aws:sns:us-east-1:123456789012:my-topic', $arn);
    }

    public function testCreateTopicThrowsExceptionIfItFails(): void
    {
        $this->expectException(MononokeException::class);

        $this->mockSnsClient->method('__call')
            ->with('createTopic', [['Name' => 'fail-topic']])
            ->willThrowException(
                new AwsException("fail", $this->createMock(\Aws\CommandInterface::class))
            );

        $this->service->create('fail-topic');
    }

    public function testNotifyWithString(): void
    {
        $this->mockSnsClient->expects($this->once())
            ->method('__call')
            ->with(
                'publish',
                $this->callback(function ($args) {
                    return isset($args[0]['TopicArn'], $args[0]['Message']) &&
                        $args[0]['TopicArn'] === 'arn:aws:sns:test' &&
                        $args[0]['Message'] === 'Hello World';
                })
            )
            ->willReturn(['MessageId' => 'abc123']);

        $this->service = new SnsService($this->mockSnsClient);

        $this->service->notify('arn:aws:sns:test', 'Hello World');
    }

    public function testNotifyWithArray(): void
    {
        $message = ['msg' => 'Hello'];

        $this->mockSnsClient->expects($this->once())
            ->method('__call')
            ->with(
                'publish',
                $this->callback(function ($args) use ($message) {
                    $decoded = json_decode($args[0]['Message'], true, 512, JSON_THROW_ON_ERROR);
                    return $args[0]['TopicArn'] === 'arn:aws:sns:test' &&
                        $decoded === $message;
                })
            )
            ->willReturn(['MessageId' => 'abc123']);

        $this->service = new SnsService($this->mockSnsClient);

        $this->service->notify('arn:aws:sns:test', $message);
    }

    public function testNotifyInvalidJsonThrowsException(): void
    {
        $this->expectException(MononokeException::class);

        $invalid = "\xB1\x31"; // invalid UTF-8

        $this->service->notify('arn:aws:sns:test', ["data" => $invalid]);
    }

    public function testSubscribe(): void
    {
        $this->mockSnsClient->expects($this->once())
            ->method('__call')
            ->with(
                'subscribe'
            )->willReturn([
                'Protocol' => 'sqs',
                'TopicArn' => 'arn:aws:sns:test',
                'Endpoint' => 'arn:aws:sqs:test',
            ]);

        $this->service->subscribe('arn:aws:sns:test', 'arn:aws:sqs:test');
    }

    public function testSubscribeThrowsException(): void
    {
        $this->expectException(MononokeException::class);

        $this->mockSnsClient->expects($this->once())
            ->method('__call')
            ->with(
                'subscribe'
            )->willThrowException(
                new AwsException("fail", $this->createMock(\Aws\CommandInterface::class))
            );

        $this->service->subscribe('arn:aws:sns:test', 'arn:aws:sqs:test');
    }
}
