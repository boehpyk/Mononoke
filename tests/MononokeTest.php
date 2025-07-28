<?php

declare(strict_types=1);

use Aws\Result;
use Aws\Sns\SnsClient;
use Kekke\Mononoke\Mononoke;
use Kekke\Mononoke\Exceptions\MononokeException;
use PHPUnit\Framework\TestCase;

use function React\Promise\resolve;
use function React\Promise\reject;

final class MononokeTest extends TestCase
{
    private SnsClient $mockClient;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(SnsClient::class);
        Mononoke::setSnsClient($this->mockClient);
    }

    public function testSnsPublishSuccess(): void
    {
        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with(
                'publish',
                [[
                    'TopicArn' => 'arn:aws:sns:localstack:test-topic',
                    'Message' => '{"msg":"Hello"}'
                ]]
            )
            ->willReturn(resolve(new Result(['MessageId' => '123'])));

        $this->expectNotToPerformAssertions();

        Mononoke::setSnsClient($this->mockClient);
        Mononoke::SnsPublish('arn:aws:sns:localstack:test-topic', ['msg' => 'Hello']);
    }

    public function testSnsPublishFailure(): void
    {
        $this->mockClient->expects($this->once())
            ->method('__call')
            ->with(
                'publish',
                [[
                    'TopicArn' => 'arn:aws:sns:localstack:test-topic',
                    'Message' => '{"msg":"Error"}'
                ]]
            )
            ->willReturn(reject(new Exception('Publish failed')));

        $this->expectException(MononokeException::class);

        Mononoke::SnsPublish('arn:aws:sns:localstack:test-topic', ['msg' => 'Error']);
    }

    public function testSnsPublishThrowsOnInvalidJson(): void
    {
        // Create invalid JSON payload by using a non-UTF8 character
        $badData = ["\xB1\x31" => "bad"];

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessageMatches('/Failed to encode SNS message to JSON:/');

        Mononoke::SnsPublish('arn:aws:sns:localstack:test-topic', $badData);
    }
}
