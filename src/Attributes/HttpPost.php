<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HttpPost
{
    public function __construct(public string $path) {}
}
