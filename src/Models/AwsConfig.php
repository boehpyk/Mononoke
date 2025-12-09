<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

final class AwsConfig extends ImmutableConfig
{
    public function __construct(
        public readonly int $sqsPollTimeInSeconds = 5,
        public readonly int $dlqMaxRetryCount = 3,
        public readonly bool $autoCreateResources = true
    ) {
        parent::__construct();
    }
}
