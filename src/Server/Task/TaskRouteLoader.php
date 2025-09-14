<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Task;

use Kekke\Mononoke\Reflection\AttributeScanner;

class TaskRouteLoader
{
    /**
     * @return list<array{string, callable(): mixed}>
     */
    public function load(object $service): array
    {
        $scanner = new AttributeScanner($service);
        $httpMethods = $scanner->getMethodsWithAttribute(\Kekke\Mononoke\Attributes\Task::class);

        $routes = [];
        foreach ($httpMethods as $entry) {
            foreach ($entry['attributes'] as $taskAttr) {
                /** @var callable(): mixed $callable */
                $callable = [$service, $entry['method']->getName()];
                $routes[] = [
                    $taskAttr->identifier,
                    $callable
                ];
            }
        }

        return $routes;
    }
}
