<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server\Http;

use Kekke\Mononoke\Reflection\AttributeScanner;

class HttpRouteLoader
{
    /**
     * @return list<array{string, string, callable(): mixed}>
     */
    public function load(object $service): array
    {
        $scanner = new AttributeScanner($service);
        $httpMethods = $scanner->getMethodsWithAttribute(\Kekke\Mononoke\Attributes\Http::class);

        $routes = [];
        foreach ($httpMethods as $entry) {
            foreach ($entry['attributes'] as $httpAttr) {
                /** @var callable(): mixed $callable */
                $callable = [$service, $entry['method']->getName()];
                $routes[] = [
                    $httpAttr->method->value,
                    $httpAttr->path,
                    $callable
                ];
            }
        }

        return $routes;
    }
}
