<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Config;

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Reflection\AttributeScanner;

class ConfigLoader
{
    public function __construct(
        private readonly OverrideApplier $applier = new OverrideApplier()
    ) {}

    public function load(object $service): Config
    {
        $scanner = new AttributeScanner($service);
        return $scanner->getAttributeInstanceFromClass(Config::class);
    }

    public function applyOverrides(Config $config): Config
    {
        $overrides = new Overrides();

        foreach ($overrides as $override) {
            $this->applier->apply($config, $override);
        }

        return $config;
    }
}
