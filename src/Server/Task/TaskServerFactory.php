<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Task;

use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Server\Http\Traits\CreateDispatcher;
use Kekke\Mononoke\Server\Http\Traits\RouteHandler;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\ServerFactory;
use Swoole\Server;

class TaskServerFactory implements ServerFactory
{
    use CreateDispatcher, RouteHandler;

    public function create(Options $options): Server
    {
        $server = new Server("0.0.0.0", $options->config->http->port);
        $this->registerListeners($server, $options);

        return $server;
    }

    public function extend(Server &$server, Options $options): void
    {
        $this->registerListeners($server, $options);
    }

    private function registerListeners(Server &$server, Options $options): void
    {
        try {
            $server->on('task', function (Server $server, $task_id, $reactorId, $data) use ($options) {
                foreach ($options->taskRoutes as [$identifier, $callable]) {
                    if ($identifier === $data['identifier']) {
                        ($callable)($data['data']);
                    }
                }
            });
            $server->on('receive', function () {
                // Needed to start server
            });
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start HTTP server: {$e->getMessage()}", 0, $e);
        }
    }
}
