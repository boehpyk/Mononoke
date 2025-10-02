<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

use Kekke\Mononoke\Exceptions\ImmutableConfigIsNotImmutableException;
use Kekke\Mononoke\Exceptions\MononokeException;

class ImmutableConfig
{
    public function __construct()
    {
        $reflection = new \ReflectionClass(static::class);

        foreach ($reflection->getProperties() as $prop) {
            if (!$prop->isPromoted() || !$prop->isReadOnly()) {
                throw new ImmutableConfigIsNotImmutableException(sprintf(
                    'Property $%s in %s must be promoted and readonly in constructor',
                    $prop->getName(),
                    static::class
                ));
            }
        }
    }

    public function with(string $prop, mixed $value): static
    {
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $args = [];
        
        if (is_null($constructor)) {
            throw new MononokeException(sprintf("Invalid constructor in subclass %s of ImmutableConfig", static::class));
        }
        
        $found = false;
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if ($name === $prop) {
                $args[] = $value;
                $found = true;
            } else {
                $args[] = $this->{$name};
            }
        }

        if (!$found) {
            throw new MononokeException(sprintf("Property '%s' does not exist in %s", $prop, static::class));
        }

        /** @var static */
        return $reflection->newInstanceArgs($args);
    }
}
