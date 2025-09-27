<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\WebSocket;

use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\ServerFactory;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server;

class WebSocketServerFactory implements ServerFactory
{
    public function create(Options $options): SwooleServer
    {
        $server = new Server("0.0.0.0", $options->config->http->port);
        $this->registerListeners($server, $options);

        return $server;
    }

    public function extend(SwooleServer &$server, Options $options): void
    {
        $this->registerListeners($server, $options);
    }

    public function registerListeners(SwooleServer &$server, Options $options): void
    {
        try {
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
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start server: {$e->getMessage()}", 0, $e);
        }
    }
}
