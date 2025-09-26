<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

class MononokeConfig
{
    public function __construct(public string $serviceName = 'default', public int $numberOfTaskWorkers = 2) {}
}
