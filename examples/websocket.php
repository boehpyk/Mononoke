<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\WebSocket;
use Kekke\Mononoke\Enums\WebSocketEvent;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[WebSocket(WebSocketEvent::OnOpen)]
    public function onOpened($server, $fd)
    {
        Logger::info("Opened!");
    }

    #[WebSocket(WebSocketEvent::OnMessage)]
    public function onMessage($server, $fd, $message)
    {
        Logger::info("Message received", ['message' => $message]);
        $server->push($fd, "Received: $message");
    }

    #[WebSocket(WebSocketEvent::OnClose)]
    public function onClose($server, $fd)
    {
        Logger::info("Connection closed", ['fd' => $fd]);
    }
}
