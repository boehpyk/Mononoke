<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

/**
 * Enum with runtime events
 */
enum RuntimeEvent: string
{
    case OnStart = 'onStart';
    case OnShutdown = 'onShutdown';
}
