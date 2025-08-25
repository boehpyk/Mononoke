<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Attributes;

use Attribute;
use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Exceptions\MononokeException;

/**
 * Attribute to define an HTTP endpoint.
 *
 * When applied to a method, this attribute registers an HTTP route using the specified
 * HTTP method and path. If at least one endpoint is registered, Mononoke will initialize
 * a web server via ReactPHP to handle incoming HTTP requests.
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
