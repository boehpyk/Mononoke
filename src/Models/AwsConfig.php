<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

class AwsConfig
{
    public function __construct(public int $sqsPollTimeInSeconds = 5, public int $dlqMaxRetryCount = 3) {}
}
