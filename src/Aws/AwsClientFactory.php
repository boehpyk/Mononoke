<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Enums\ClientType;

class AwsClientFactory
{
    /** @var array<string, SnsClient|SqsClient> */
    private static array $cache = [];

    /**
     * Create a AwsClient of ClientType
     */
    public static function create(ClientType $clientType): SnsClient|SqsClient
    {
        if (isset(self::$cache[$clientType->value])) {
            return self::$cache[$clientType->value];
        }

        $commonConfig = [
            'version' => 'latest',
        ];

        self::$cache[$clientType->value] = match ($clientType) {
            ClientType::SNS => new SnsClient($commonConfig),
            ClientType::SQS => new SqsClient($commonConfig),
        };

        return self::$cache[$clientType->value];
    }
}
