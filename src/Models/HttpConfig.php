<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

final class HttpConfig
{
    public function __construct(
        public readonly int $port = 80
    ) {}
}
