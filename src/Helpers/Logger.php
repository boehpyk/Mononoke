<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Helpers;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wrapper for Monolog
 */
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

    /**
     * Set a customer Monolog logger interface
     */
    public static function setLogger(LoggerInterface $customLogger): void
    {
        self::$logger = $customLogger instanceof MonologLogger ? $customLogger : new MonologLogger('mononoke');
    }

    /**
     * Debug level log
     */
    public static function debug(string $message, array $context = []): void
    {
        self::init();
        self::$logger->debug($message, $context);
    }

    /**
     * Info level log
     */
    public static function info(string $message, array $context = []): void
    {
        self::init();
        self::$logger->info($message, $context);
    }

    /**
     * Warning level log
     */
    public static function warning(string $message, array $context = []): void
    {
        self::init();
        self::$logger->warning($message, $context);
    }

    /**
     * Error level log
     */
    public static function error(string $message, array $context = []): void
    {
        self::init();
        self::$logger->error($message, $context);
    }

    /**
     * Exception level log
     */
    public static function exception(string $message, Throwable $exception, array $context = []): void
    {
        self::init();
        if (!isset($context['Exception'])) {
            $context['Exception'] = $exception->getMessage();
        }
        self::$logger->error($message, $context);
    }

    /**
     * Custom level log
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();
        self::$logger->log($level, $message, $context);
    }
}
