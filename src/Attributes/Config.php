<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Models\AwsConfig;
use Kekke\Mononoke\Models\HttpConfig;
use Kekke\Mononoke\Models\MononokeConfig;

/**
 * Attribute to define config options.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Config
{
    public function __construct(
        public MononokeConfig $mononoke = new MononokeConfig(),
        public AwsConfig $aws = new AwsConfig(),
        public HttpConfig $http = new HttpConfig(),
    ) {}
}
