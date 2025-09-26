<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Reflection;

use Kekke\Mononoke\Attributes\Config;
use ReflectionClass;
use ReflectionMethod;

class AttributeScanner
{
    /** @var array<string, array<class-string, array<object>>> */
    private array $cache = [];

    /**
     * Constructor for AttributeScanner
     * Takes a target object and scans all methods of the target and caches the attributes of these methods
     */
    public function __construct(private readonly object $target)
    {
        $this->cacheAttributes();
    }

    public function getAttributeInstanceFromClass(string $attributeClass): Config
    {
        $reflector = new ReflectionClass($this->target);

        $attributes = $reflector->getAttributes($attributeClass);
        if (empty($attributes)) {
            return new Config();
        }

        $config = $attributes[0]->newInstance();

        if ($config instanceof Config) {
            return $config;
        }

        return new Config();
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
