<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Http;

use FastRoute\RouteCollector;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;

use function FastRoute\simpleDispatcher;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Server as SwooleHttpServer;

class HttpServerFactory
{
    /**
     * @param array<array{string, string, callable}> $routes
     */
    public function create(array $routes, int $port): SwooleHttpServer
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) use ($routes) {
            foreach ($routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });

        if (count($routes) > 0) {
            Logger::info("HTTP routes registered", ['number_of_routes' => count($routes)]);
        }

        try {
            $server = new SwooleHttpServer("0.0.0.0", $port);

            $server->on("request", function (SwooleRequest $request, SwooleResponse $response) use ($dispatcher) {
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
                        $this->handleFoundRoute($handler, $vars, $response);
                        break;

                    default:
                        $response->status(500);
                        $response->header("Content-Type", "text/plain");
                        $response->end("Unexpected error");
                        break;
                }
            });

            return $server;
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start HTTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function handleFoundRoute(callable $handler, array $vars, SwooleResponse $response): void
    {
        $result = call_user_func_array($handler, $vars);

        switch (true) {
            case $result instanceof SwooleResponse:
                // If handler already manipulated the response, just return
                return;

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
