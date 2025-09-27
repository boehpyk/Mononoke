<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server;

use Kekke\Mononoke\Attributes\Config;

class Options
{
    /**
     * @param array<array{string, string, callable}> $httpRoutes
     * @param array<array{\Kekke\Mononoke\Enums\WebSocketEvent, callable}> $wsRoutes
     * @param array<array{string, callable}> $taskRoutes
     */
    public function __construct(public array $httpRoutes = [], public array $wsRoutes = [], public array $taskRoutes = [], public Config $config = new Config()) {}
}
