<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server;

use Swoole\Server;

class Options
{
    /**
     * @param array<array{string, string, callable}> $httpRoutes
     * @param array<array{\Kekke\Mononoke\Enums\WebSocketEvent, callable}> $wsRoutes
     * @param array<array{string, callable}> $taskRoutes
     */
    public function __construct(public Server &$server, public array $httpRoutes = [], public array $wsRoutes = [], public array $taskRoutes = []) {}
}
