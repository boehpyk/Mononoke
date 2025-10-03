<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Helpers;

use Kekke\Mononoke\Attributes\Hook;
use Kekke\Mononoke\Enums\RuntimeEvent;
use ReflectionClass;

class HookDispatcher
{
    /** @var array<string, list<callable>> */
    private array $hooks = [];

    public function __construct(object $service)
    {
        $this->register($service);
    }

    public function register(object $service): void
    {
        $reflection = new ReflectionClass($service);
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Hook::class) as $attr) {
                $hook = $attr->newInstance();
                /** @var callable(): mixed $callable */
                $callable = [$service, $method->getName()];
                $this->hooks[$hook->event->value][] = $callable;
            }
        }
    }

    public function trigger(RuntimeEvent $event): void
    {
        foreach ($this->hooks[$event->value] ?? [] as $callable) {
            ($callable)();
        }
    }
}
