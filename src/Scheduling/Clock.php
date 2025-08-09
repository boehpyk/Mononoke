<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Scheduling;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
