<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

final class AwsConfig
{
    public function __construct(
        public readonly int $sqsPollTimeInSeconds = 5,
        public readonly int $dlqMaxRetryCount = 3
    ) {}
}
