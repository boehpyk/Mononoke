<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Aws;

use Closure;

class SqsMessageHandler
{
    public function __construct(private Closure $closure) {}

    public function handle(string $body): void
    {
        ($this->closure)($body);
    }
}
