<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Transport;

use Swoole\Http\Server;

/**
 * BackgroundTask helper methods
 */
class BackgroundTask
{
    public function __construct(private Server $server) {}

    public function dispatch(string $identifier, mixed $data): void
    {
        $this->server->task(['identifier' => $identifier, 'data' => $data]);
    }
}
