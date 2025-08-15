<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Reflection;

use ReflectionClass;

class AttributeScanner
{
    private array $cache = [];

    public function __construct(private readonly object $target)
    {
        $this->cacheAttributes();
    }

    public function getMethodsWithAttribute(string $attributeClass): array
    {
        $result = array_map(function ($attributeGroup, $methodName) use ($attributeClass) {
            if (isset($attributeGroup[$attributeClass])) {
                return [
                    'method' => (new ReflectionClass($this->target))->getMethod($methodName),
                    'attributes' => $attributeGroup[$attributeClass]
                ];
            }
        }, array_values($this->cache), array_keys($this->cache));

        return array_filter($result);
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
