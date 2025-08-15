<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Enums\ClientType;

class AwsClientFactory
{
    /**
     * Create a AwsClient of ClientType
     */
    public static function create(ClientType $clientType): SnsClient|SqsClient
    {
        $commonConfig = [
            'version' => 'latest',
        ];

        return match ($clientType) {
            ClientType::SNS => new SnsClient($commonConfig),
            ClientType::SQS => new SqsClient($commonConfig),
        };
    }
}
