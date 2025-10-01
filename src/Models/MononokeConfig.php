<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

final class MononokeConfig
{
    public function __construct(
        public readonly string $serviceName = 'default',
        public readonly int $numberOfTaskWorkers = 2
    ) {}
}
