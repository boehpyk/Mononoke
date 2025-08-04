<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Exceptions\MononokeException;

/**
 * Http attribute
 * This attribute creates a HTTP endpoint
 * Mononoke will create a webserver via ReactPHP if an endpoint has been registered
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Http
{
    public HttpMethod $method;

    public function __construct(string|HttpMethod $method, public string $path)
    {
        if ($method instanceof HttpMethod) {
            $this->method = $method;
            return;
        }

        try {
            $this->method = HttpMethod::from(strtoupper($method));
        } catch (\ValueError $e) {
            throw new MononokeException("Invalid HTTP method: {$method}");
        }
    }
}
