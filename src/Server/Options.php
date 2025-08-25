<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server;

class Options
{
    /**
     * @param array<array{string, string, callable}> $httpRoutes
     * @param array<array{\Kekke\Mononoke\Enums\WebSocketEvent, callable}> $wsRoutes
     */
    public function __construct(public int $port, public array $httpRoutes = [], public array $wsRoutes = []) {}
}
