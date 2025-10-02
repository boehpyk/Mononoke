<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

use Kekke\Mononoke\Exceptions\MononokeException;

class ImmutableConfig
{
    public function with(string $prop, mixed $value): static
    {
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $args = [];

        if (is_null($constructor)) {
            throw new MononokeException("Invalid constructor in subclass of ImmutableConfig");
        }

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $args[] = ($name === $prop) ? $value : $this->{$name};
        }

        /** @var static */
        return $reflection->newInstanceArgs($args);
    }
}
