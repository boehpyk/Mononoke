<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Exceptions\MononokeException;

/**
 * Attribute to define a WebSocket event listener.
 *
 * When applied to a method, this attribute registers a handler for the specified
 * WebSocket event (e.g., OnOpen, OnMessage, OnClose). If at least one WebSocket
 * listener is registered, Mononoke will initialize a WebSocket server to handle
 * incoming client connections and events.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class WebSocket
{
    public WebSocketEvent $event;

    public function __construct(string|WebSocketEvent $event)
    {
        if ($event instanceof WebSocketEvent) {
            $this->event = $event;
            return;
        }

        try {
            $this->event = WebSocketEvent::from(strtoupper($event));
        } catch (\ValueError $e) {
            throw new MononokeException("Invalid WebSocket event: {$event}");
        }
    }
}
