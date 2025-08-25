<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

/**
 * Enum with valid websocket events
 */
enum WebSocketEvent: string
{
    case OnMessage = 'message';
    case OnOpen = 'open';
    case OnClose = 'close';
}