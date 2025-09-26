<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

class HttpConfig
{
    public function __construct(public int $port = 80) {}
}
