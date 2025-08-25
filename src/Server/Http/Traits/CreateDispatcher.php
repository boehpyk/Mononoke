<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Http\Traits;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Kekke\Mononoke\Helpers\Logger;

use function FastRoute\simpleDispatcher;

trait CreateDispatcher
{
    /**
     * @param array<array{string, string, callable(): mixed}> $routes
     */
    public function getDispatcher(array $routes): Dispatcher
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) use ($routes) {
            foreach ($routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });

        if (count($routes) > 0) {
            Logger::info("HTTP routes registered", ['number_of_routes' => count($routes)]);
        }

        return $dispatcher;
    }
}