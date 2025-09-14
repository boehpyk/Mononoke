<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\WebSocket;

use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\ServerFactory;
use Swoole\WebSocket\Server;

class WebSocketServerFactory implements ServerFactory
{
    public function create(Options $options): void
    {
        try {
            $options->server->on("open", function (Server $server, $request) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnOpen) {
                        ($callable)($server, $request->fd);
                    }
                }
            });

            $options->server->on("message", function (Server $server, $request) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnMessage) {
                        ($callable)($server, $request->fd, $request->data);
                    }
                }
            });

            $options->server->on("close", function (Server $server, $fd) use ($options) {
                foreach ($options->wsRoutes as [$method, $callable]) {
                    if ($method === WebSocketEvent::OnClose) {
                        ($callable)($server, $fd);
                    }
                }
            });
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start server: {$e->getMessage()}", 0, $e);
        }
    }
}
