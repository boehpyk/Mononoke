<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\RuntimeEvent;

#[Attribute(Attribute::TARGET_METHOD)]
class Hook
{
    public function __construct(public RuntimeEvent $event) {}
}
