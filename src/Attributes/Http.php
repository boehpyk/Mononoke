<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Exceptions\MononokeException;

#[Attribute(Attribute::TARGET_METHOD)]
class Http
{
    public HttpMethod $method;

    public function __construct(string $method, public string $path)
    {
        try {
            $this->method = HttpMethod::from(strtoupper($method));
        } catch (\ValueError $e) {
            throw new MononokeException("Invalid HTTP method: {$method}");
        }
    }
}
