<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Exceptions\MononokeException;

/**
 * Attribute to define an HTTP endpoint.
 *
 * When applied to a method, this attribute registers an HTTP route using the specified
 * HTTP method and path. If at least one endpoint is registered, Mononoke will initialize
 * a web server via ReactPHP to handle incoming HTTP requests.
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
