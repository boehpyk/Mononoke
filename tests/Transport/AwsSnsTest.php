<?php

declare(strict_types=1);

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Sns\SnsClient;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Transport\AwsSns;
use PHPUnit\Framework\TestCase;

final class AwsSnsTest extends TestCase
{
    /** @var SnsClient&\PHPUnit\Framework\MockObject\MockObject */
    private SnsClient|\PHPUnit\Framework\MockObject\MockObject $mockClient;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(SnsClient::class);
        AwsSns::setSnsClient($this->mockClient);
    }

    public function testSnsPublishSuccess(): void
    {
        $this->mockClient->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'createTopic' && isset($args[0]['Name'])) {
                    return new Result(['TopicArn' => 'arn:aws:sns:localstack:test-topic']);
                }

                if ($method === 'publish' && isset($args[0]['TopicArn'])) {
                    return new Result(['MessageId' => '123']);
                }

                throw new Exception("Unexpected method call: $method");
            });

        AwsSns::setSnsClient($this->mockClient);
        AwsSns::publish('arn:aws:sns:localstack:test-topic', ['msg' => 'Hello']);
    }

    public function testSnsPublishFailureThrowsMononokeException(): void
    {
        $this->mockClient->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'createTopic' && isset($args[0]['Name'])) {
                    return new Result(['TopicArn' => 'arn:aws:sns:localstack:test-topic']);
                }

                if ($method === 'publish') {
                    // Simulate AWS SDK throwing an exception on publish
                    throw new AwsException(
                        'Simulated publish failure',
                        $this->createMock(\Aws\CommandInterface::class)
                    );
                }

                throw new \Exception("Unexpected method call: $method");
            });

        AwsSns::setSnsClient($this->mockClient);

        $this->expectException(\Kekke\Mononoke\Exceptions\MononokeException::class);
        $this->expectExceptionMessage('AWS SNS publish failed');

        // Call your method, which should throw
        AwsSns::publish('test-topic', ['msg' => 'Hello']);
    }

    public function testSnsPublishThrowsOnInvalidJson(): void
    {
        // Create invalid JSON payload by using a non-UTF8 character
        $badData = ["\xB1\x31" => "bad"];

        $this->expectException(MononokeException::class);
        $this->expectExceptionMessageMatches('/Failed to encode SNS message to JSON:/');

        AwsSns::publish('arn:aws:sns:localstack:test-topic', $badData);
    }
}
