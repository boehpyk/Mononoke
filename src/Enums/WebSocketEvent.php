<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

/**
 * Enum with valid http methods
 */
enum WebSocketEvent: string
{
    case OnMessage = 'message';
    case OnOpen = 'open';
    case OnClose = 'close';
}