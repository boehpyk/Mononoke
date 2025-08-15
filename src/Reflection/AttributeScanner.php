<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Reflection;

use ReflectionClass;
use ReflectionMethod;

class AttributeScanner
{
    /** @var array<string, array<class-string, array<object>>> */
    private array $cache = [];

    public function __construct(private readonly object $target)
    {
        $this->cacheAttributes();
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return array<array{method: ReflectionMethod, attributes: array<T>}>
     */
    public function getMethodsWithAttribute(string $attributeClass): array
    {
        $results = [];

        foreach ($this->cache as $methodName => $attributeGroup) {
            if (!isset($attributeGroup[$attributeClass])) {
                continue;
            }

            /** @var list<T> $attributes */
            $attributes = $attributeGroup[$attributeClass];

            $results[] = [
                'method' => (new ReflectionClass($this->target))->getMethod($methodName),
                'attributes' => $attributes,
            ];
        }

        return $results;
    }

    private function cacheAttributes(): void
    {
        $reflector = new ReflectionClass($this->target);

        foreach ($reflector->getMethods() as $method) {
            $attributeGroup = [];
            foreach ($method->getAttributes() as $attr) {
                $attributeGroup[$attr->getName()][] = $attr->newInstance();
            }
            $this->cache[$method->getName()] = $attributeGroup;
        }
    }
}
