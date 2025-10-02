<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Config;

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Models\Override;

class OverrideApplier
{
    /**
     * Applies an override to the given config file
     * Does so by checking if the env variable exists (as specified in the Override model)
     * And then making sure that the configName and varName exists on the config object
     */
    public function apply(Config &$config, Override $override): void
    {
        $envValue = getenv($override->envVar);

        if ($envValue === false) {
            return;
        }

        if (!property_exists($config, $override->configName)) {
            return;
        }

        $configPart = $config->{$override->configName};

        if (!is_object($configPart) || !property_exists($configPart, $override->varName)) {
            return;
        }

        $type = gettype($configPart->{$override->varName});
        $casted = $this->castToType($envValue, $type);

        $config->{$override->configName} = $config->{$override->configName}->with($override->varName, $casted);
    }

    private function castToType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'double'  => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'string'  => (string) $value,
            'array'   => (array) $value,
            default   => $value,
        };
    }
}
