<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;

/**
 * Attribute to define a background task
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Task
{
    public function __construct(public string $identifier) {}
}
