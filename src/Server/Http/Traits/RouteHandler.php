<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Http\Traits;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Kekke\Mononoke\Helpers\Logger;
use Swoole\Http\Response;

trait RouteHandler
{
    /**
     * @param array<string, string> $vars
     */
    public function handleRoute(callable $handler, array $vars, Response $response): void
    {
        $result = call_user_func_array($handler, $vars);

        switch (true) {
            case $result instanceof Psr7Response:
                $response->status($result->getStatusCode());
                foreach ($result->getHeaders() as $headerName => $headerValue) {
                    $response->header($headerName, $headerValue);
                }
                $response->end($result->getBody()->getContents());
                break;

            case is_array($result):
                $response->status(200);
                $response->header("Content-Type", "application/json");
                $response->end(json_encode($result, JSON_THROW_ON_ERROR));
                break;

            default:
                $response->status(200);
                $response->header("Content-Type", "text/plain");
                $response->end((string) $result);
                break;
        }
    }
}
