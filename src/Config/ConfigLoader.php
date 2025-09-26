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
}
