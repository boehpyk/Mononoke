<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Tests\Aws;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Aws\AwsClientFactory;
use Kekke\Mononoke\Enums\ClientType;
use PHPUnit\Framework\TestCase;

class AwsClientFactoryTest extends TestCase
{
    public function testCreateSqsClient(): void
    {
        $client1 = AwsClientFactory::create(ClientType::SQS);
        $client2 = AwsClientFactory::create(ClientType::SQS);

        $this->assertInstanceOf(SqsClient::class, $client1);
        $this->assertSame($client1, $client2);
    }

    public function testCreateSnsClient(): void
    {
        $client1 = AwsClientFactory::create(ClientType::SNS);
        $client2 = AwsClientFactory::create(ClientType::SNS);

        $this->assertInstanceOf(SnsClient::class, $client1);
        $this->assertSame($client1, $client2);
    }

    public function testSqsAndSnsAreDifferentInstances(): void
    {
        $sqsClient = AwsClientFactory::create(ClientType::SQS);
        $snsClient = AwsClientFactory::create(ClientType::SNS);

        $this->assertNotSame($sqsClient, $snsClient);
    }
}
