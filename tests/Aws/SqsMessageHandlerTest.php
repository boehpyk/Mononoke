<?php

declare(strict_types=1);

namespace Tests\Aws;

use Kekke\Mononoke\Aws\SqsMessageHandler;
use PHPUnit\Framework\TestCase;

final class SqsMessageHandlerTest extends TestCase
{
    public function testClosureIsCalledWithMessageBody(): void
    {
        $received = null;

        $handler = new SqsMessageHandler(function (string $body) use (&$received) {
            $received = $body;
        });

        $handler->handle('test-message');

        self::assertSame('test-message', $received);
    }

    public function testClosureCanPerformCustomLogic(): void
    {
        $log = [];

        $handler = new SqsMessageHandler(function (string $body) use (&$log) {
            $log[] = strtoupper($body);
        });

        $handler->handle('hello');
        $handler->handle('world');

        self::assertSame(['HELLO', 'WORLD'], $log);
    }
}
