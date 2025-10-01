<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Models;

class Override
{
    public function __construct(public string $configName, public string $varName, public string $envVar) {}
}
