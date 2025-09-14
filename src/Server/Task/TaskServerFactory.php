<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Task;

use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Server\Http\Traits\CreateDispatcher;
use Kekke\Mononoke\Server\Http\Traits\RouteHandler;
use Kekke\Mononoke\Server\Options;
use Swoole\Server;

class TaskServerFactory
{
    use CreateDispatcher, RouteHandler;

    public function create(Options $options): void
    {
        try {
            $options->server->on('task', function (Server $server, $task_id, $reactorId, $data) use ($options) {
                foreach ($options->taskRoutes as [$identifier, $callable]) {
                    if ($identifier === $data['identifier']) {
                        ($callable)($data['data']);
                    }
                }
            });
            $options->server->on('receive', function () {
                // Needed to start server
            });
        } catch (\Throwable $e) {
            throw new MononokeException("Unable to start HTTP server: {$e->getMessage()}", 0, $e);
        }
    }
}
