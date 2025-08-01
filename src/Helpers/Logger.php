<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Helpers;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

class Logger
{
    private static ?MonologLogger $logger = null;

    private static function init(): void
    {
        if (self::$logger !== null) {
            return;
        }

        $stream = new StreamHandler('php://stdout', \Monolog\Level::Debug);

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            null,
            true,
            true
        );
        $stream->setFormatter($formatter);

        self::$logger = new MonologLogger('mononoke');
        self::$logger->pushHandler($stream);
    }

    public static function setLogger(LoggerInterface $customLogger): void
    {
        self::$logger = $customLogger instanceof MonologLogger ? $customLogger : new MonologLogger('mononoke');
    }

    public static function debug(string $message, array $context = []): void
    {
        self::init();
        self::$logger->debug($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::init();
        self::$logger->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::init();
        self::$logger->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::init();
        self::$logger->error($message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();
        self::$logger->log($level, $message, $context);
    }
}
