<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

final class HttpConfig extends ImmutableConfig
{
    public function __construct(
        public readonly int $port = 80
    ) {}
}
