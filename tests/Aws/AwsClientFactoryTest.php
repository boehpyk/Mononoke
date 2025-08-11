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
        $client = AwsClientFactory::create(ClientType::SQS);
        $this->assertInstanceOf(SqsClient::class, $client);
    }

    public function testCreateSnsClient(): void
    {
        $client = AwsClientFactory::create(ClientType::SNS);
        $this->assertInstanceOf(SnsClient::class, $client);
    }
}
