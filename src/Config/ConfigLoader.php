<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Config;

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Reflection\AttributeScanner;

class ConfigLoader
{
    public function load(object $service): Config
    {
        $scanner = new AttributeScanner($service);
        return $scanner->getAttributeInstanceFromClass(Config::class);
    }

    public function applyOverrides(Config $config): Config
    {
        $overrides = new Overrides();

        foreach ($overrides as $override) {
            $value = getenv($override->envVar);

            if ($value === false) {
                continue;
            }

            $type = gettype($config->{$override->configName}->{$override->varName});

            $casted = match ($type) {
                'integer' => (int) $value,
                'double'  => (float) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                'string'  => (string) $value,
                'array'   => (array) $value,
                default   => $value,
            };

            $config->{$override->configName}->{$override->varName} = $casted;
        }

        return $config;
    }
}
