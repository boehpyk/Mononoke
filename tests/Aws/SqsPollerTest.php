<?php

declare(strict_types=1);

namespace Tests\Aws;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Aws\SqsPoller;
use Kekke\Mononoke\Helpers\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SqsPollerTest extends TestCase
{
    /** @var SqsClient&MockObject */
    private SqsClient $client;

    private string $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(SqsClient::class);
    }

    public function testPollReturnsMessages(): void
    {
        $messages = [
            ['MessageId' => '1', 'Body' => 'test1'],
            ['MessageId' => '2', 'Body' => 'test2'],
        ];

        $this->client->expects(self::once())
            ->method('__call')
            ->with(
                'receiveMessage',
                [[
                    'QueueUrl' => $this->queueUrl,
                    'MaxNumberOfMessages' => 5,
                    'WaitTimeSeconds' => 0
                ]]
            )
            ->willReturn(new Result(['Messages' => $messages]));

        $poller = new SqsPoller($this->client, $this->queueUrl);

        self::assertSame($messages, $poller->poll());
    }

    public function testPollReturnsEmptyArrayWhenNoMessages(): void
    {
        $this->client->expects(self::once())
            ->method('__call')
            ->with('receiveMessage', $this->anything())
            ->willReturn(new Result([]));

        $poller = new SqsPoller($this->client, $this->queueUrl);

        self::assertSame([], $poller->poll());
    }

    public function testPollHandlesAwsException(): void
    {
        $this->client->expects(self::once())
            ->method('__call')
            ->with('receiveMessage', $this->anything())
            ->willThrowException(new AwsException(
                'AWS error',
                $this->createMock(CommandInterface::class)
            ));

        $poller = new SqsPoller($this->client, $this->queueUrl);
        $result = $poller->poll();

        self::assertSame([], $result);
    }

    public function testDeleteCallsDeleteMessage(): void
    {
        $receiptHandle = 'abc123';

        $this->client->expects(self::once())
            ->method('__call')
            ->with(
                'deleteMessage',
                [[
                    'QueueUrl' => $this->queueUrl,
                    'ReceiptHandle' => $receiptHandle
                ]]
            )
            ->willReturn(new Result([]));

        $poller = new SqsPoller($this->client, $this->queueUrl);
        $poller->delete($receiptHandle);
    }
}
