<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Http;

use FastRoute\RouteCollector;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;

use function FastRoute\simpleDispatcher;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class HttpServerFactory
{
    /**
     * @param array<array{string, string, callable}> $routes
     */
    public function create(array $routes): SocketServer
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) use ($routes) {
            foreach ($routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });

        if (count($routes) > 0) {
            Logger::info("HTTP routes registered", ['number_of_routes' => count($routes)]);
        }

        $server = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher) {
            $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

            switch ($routeInfo[0]) {
                case \FastRoute\Dispatcher::NOT_FOUND:
                    return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    return $this->handleFoundRoute($handler, $vars);
            }

            return new Response(500, ['Content-Type' => 'text/plain'], 'Unexpected error');
        });

        try {
            $socket = new SocketServer('0.0.0.0:80');
            $server->listen($socket);
            return $socket;
        } catch (\RuntimeException $e) {
            throw new MononokeException("Unable to start HTTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function handleFoundRoute(callable $handler, array $vars): Response
    {
        $result = call_user_func_array($handler, $vars);

        return match (true) {
            $result instanceof Response =>
            $result,
            is_array($result) =>
            new Response(200, ['Content-Type' => 'application/json'], json_encode($result, JSON_THROW_ON_ERROR)),
            default =>
            new Response(200, ['Content-Type' => 'text/plain'], (string) $result)
        };
    }
}
