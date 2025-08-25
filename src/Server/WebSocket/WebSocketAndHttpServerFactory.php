<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\WebSocket;

use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Server\Http\Traits\CreateDispatcher;
use Kekke\Mononoke\Server\Http\Traits\RouteHandler;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\ServerFactory;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;

class WebSocketAndHttpServerFactory implements ServerFactory
{
    use CreateDispatcher, RouteHandler;

    public function create(Options $options): Server
    {
        try {
            $server = new Server("0.0.0.0", $options->port);

            $dispatcher = $this->getDispatcher($options->httpRoutes);

            $server->on("request", function (Request $request, Response $response) use ($dispatcher) {
                $path   = $request->server['request_uri'] ?? '/';
                $method = strtoupper($request->server['request_method'] ?? 'GET');

                Logger::info("Request incoming", ['path' => $path, 'method' => $method]);

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

            $server->on("open", function (Server $server, $request) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnOpen) {
                        ($callable)($server, $request->fd);
                    }
                }
            });

            $server->on("message", function (Server $server, $request) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnMessage) {
                        ($callable)($server, $request->fd, $request->data);
                    }
                }
            });

            $server->on("close", function (Server $server, $fd) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnClose) {
                        ($callable)($server, $fd);
                    }
                }
            });

            return $server;
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start server: {$e->getMessage()}", 0, $e);
        }
    }
}
