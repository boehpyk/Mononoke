<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Http;

use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Server\Http\Traits\CreateDispatcher;
use Kekke\Mononoke\Server\Http\Traits\RouteHandler;
use Kekke\Mononoke\Server\Options;

use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Request as SwooleRequest;

class HttpServerFactory
{
    use CreateDispatcher, RouteHandler;

    public function create(Options $options): void
    {
        $dispatcher = $this->getDispatcher($options->httpRoutes);

        try {
            $options->server->on("request", function (SwooleRequest $request, SwooleResponse $response) use ($dispatcher) {
                $path   = $request->server['request_uri'] ?? '/';
                $method = strtoupper($request->server['request_method'] ?? 'GET');

                $routeInfo = $dispatcher->dispatch($method, $path);

                switch ($routeInfo[0]) {
                    case \FastRoute\Dispatcher::NOT_FOUND:
                        $response->status(404);
                        $response->header("Content-Type", "text/plain");
                        $response->end("Not found");
                        break;

                    case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                        $response->status(405);
                        $response->header("Content-Type", "text/plain");
                        $response->end("Method not allowed");
                        break;

                    case \FastRoute\Dispatcher::FOUND:
                        $handler = $routeInfo[1];
                        $vars    = $routeInfo[2];
                        $this->handleRoute($handler, $vars, $response);
                        break;

                    default:
                        $response->status(500);
                        $response->header("Content-Type", "text/plain");
                        $response->end("Unexpected error");
                        break;
                }
            });
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start HTTP server: {$e->getMessage()}", 0, $e);
        }
    }
}
