<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\WebSocket;

use Kekke\Mononoke\Reflection\AttributeScanner;

class WebSocketRouteLoader
{
    /**
     * @return list<array{\Kekke\Mononoke\Enums\WebSocketEvent, callable(): mixed}>
     */
    public function load(object $service): array
    {
        $scanner = new AttributeScanner($service);
        $httpMethods = $scanner->getMethodsWithAttribute(\Kekke\Mononoke\Attributes\WebSocket::class);

        $routes = [];
        foreach ($httpMethods as $entry) {
            foreach ($entry['attributes'] as $httpAttr) {
                /** @var callable(): mixed $callable */
                $callable = [$service, $entry['method']->getName()];
                $routes[] = [
                    $httpAttr->event,
                    $callable
                ];
            }
        }

        return $routes;
    }
}
