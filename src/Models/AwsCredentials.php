<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

class AwsCredentials
{
    public string $key;
    public string $secret;
    public string $region;
    public string $endpoint;

    private static ?self $instance = null;

    private function __construct()
    {
        $this->key = getenv('AWS_ACCESS_KEY_ID') ?: 'test';
        $this->secret = getenv('AWS_SECRET_ACCESS_KEY') ?: 'test';
        $this->region = getenv('AWS_REGION') ?: 'us-east-1';
        $this->endpoint = getenv('AWS_ENDPOINT') ?: 'http://localhost:4566';
    }

    public static function load(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}