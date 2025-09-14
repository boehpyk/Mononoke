# Mononoke

> ⚠️ **Early Alpha Notice**  
> Mononoke is currently in **early alpha** and under active development.  
> Features, and implementations may change at any time without notice.  
> It is not yet recommended for production use, but feedback and contributions are welcome.

---

Mononoke is a PHP microservice framework and utility toolkit built with modern PHP practices.  
It leverages **Swoole** for an asynchronous HTTP server and the **AWS SDK** for SNS and SQS integration,  
allowing you to build event-driven, scalable PHP applications.

---

## Features

- **AWS SNS and SQS Integration**  
  Manage SNS topics, send notifications, subscribe queues, and poll SQS messages asynchronously.

- **Swoole HTTP Server**  
  Asynchronous HTTP server with FastRoute-based routing for defining HTTP endpoints via PHP attributes.

- **WebSockets**  
  Real-time, bidirectional communication between clients and the server, powered by Swoole’s WebSocket server.

- **Background Tasks**  
  Offload blocking or long-running work to Swoole’s task workers for efficient non-blocking request handling.

- **Scheduler**  
  Register cron-like scheduled tasks using attributes.


---

## Attribute-based Listeners

Mononoke uses PHP attributes to register **HTTP routes**, **WebSocket event handlers**, **scheduled tasks**, **background tasks**, and **SNS/SQS message handlers**.  
This allows for a clean and declarative service definition.

### Example Service

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Service as MononokeService;

class Service extends MononokeService
{
    #[Http('GET', '/health')]
    public function status(): string
    {
        return "OK";
    }

    #[Http('GET', '/json')]
    public function json(): array
    {
        return ['status' => 'healthy'];
    }

    #[Http('GET', '/custom')]
    public function custom(): Response
    {
        return new Response(201, ['Authorization' => 'Bearer token'], 'Custom body');
    }

    #[Schedule(Scheduler::Daily)]
    public function scheduler(): void
    {
        // Runs once every day
    }

    #[AwsSnsSqs('topic-name', 'queue-name')]
    public function customSqsHandler(string $message): void
    {
        // Process message from "topic-name" via "queue-name"
    }
}
```

## Available attributes

### 1. `Http`

Define HTTP routes directly on service methods with the `Http` attribute.

**Usage**

- Route methods can return:
  - `string` → returned as `text/plain` (200)
  - `array` → returned as `application/json` (200, JSON-encoded)
  - `GuzzleHttp\Psr7\Response` → returned as-is
- Typical definition:

```php
    #[Http('GET', '/health')]
    public function status(): string
    {
        return "OK";
    }

    #[Http('GET', '/json')]
    public function json(): array
    {
        return ['status' => 'healthy'];
    }

    #[Http('GET', '/custom')]
    public function custom(): \GuzzleHttp\Psr7\Response
    {
        return new \GuzzleHttp\Psr7\Response(201, ['Authorization' => 'Bearer token'], 'Custom body');
    }
```

**Supported HTTP methods**

```php
    enum HttpMethod: string
    {
        case GET = 'GET';
        case POST = 'POST';
        case PUT = 'PUT';
        case DELETE = 'DELETE';
        case PATCH = 'PATCH';
        case HEAD = 'HEAD';
        case OPTIONS = 'OPTIONS';
    }
```

> Tip: If your `Http` attribute accepts the enum, you may use `Http(HttpMethod::GET, '/health')` instead of a string literal.

---

### 2. `Schedule`

Register cron-like scheduled tasks with the `Schedule` attribute. Some schedules require extra parameters (hour/minute/second). Helper methods on the enum indicate what is required.

**Basic usage**

```php
    #[Schedule(\Kekke\Mononoke\Enums\Scheduler::Daily)]
    public function dailyJob(): void
    {
        // Runs once per day
    }
```

**Time-specific examples (if supported by your attribute signature)**

```php
    // At 14:30:00 every day
    #[Schedule(\Kekke\Mononoke\Enums\Scheduler::DailyAt)]
    public function dailyAt1430(): void
    {
        // Provide hour/minute/second via your attribute's parameters or configuration
        // e.g. #[Schedule(Scheduler::DailyAt, hour: 14, minute: 30, second: 0)]
    }

    // At minute 15 every hour
    #[Schedule(\Kekke\Mononoke\Enums\Scheduler::HourlyAt)]
    public function hourlyAt15(): void
    {
        // e.g. #[Schedule(Scheduler::HourlyAt, minute: 15, second: 0)]
    }
```

**Available scheduler options**

```php
    enum Scheduler
    {
        case Daily;
        case DailyAt;
        case Hourly;
        case HourlyAt;
        case EveryMinute;
        case EveryMinuteAt;
        case EverySecond;

        public function requiresHour(): bool
        {
            return in_array($this, [Scheduler::DailyAt]);
        }

        public function requiresMinute(): bool
        {
            return in_array($this, [Scheduler::DailyAt, Scheduler::HourlyAt]);
        }

        public function requiresSecond(): bool
        {
            return in_array($this, [Scheduler::DailyAt, Scheduler::HourlyAt, Scheduler::EveryMinuteAt]);
        }
    }
```

> Notes:
> - For modes that “require” hour/minute/second, supply those via your `Schedule` attribute’s parameters (named arguments are recommended if supported).
> - If you omit a required component, validation should fail early (consider adding validation in your attribute or setup logic).

---

### 3. `AwsSnsSqs`

Subscribe a method to an SNS topic (via an SQS queue) with the `AwsSnsSqs` attribute. The framework will provision the SNS topic/SQS queue (if needed), subscribe them, and poll messages.

**Usage**

```php
    #[AwsSnsSqs('topic-name', 'queue-name')]
    public function handleMessage(string $message): void
    {
        // Process message from "topic-name" delivered through "queue-name"
        // $message is the message body (string). Parse/validate as needed.
    }
```

**Guidelines**

- Make handlers **idempotent**; SQS may deliver messages at-least-once.
- Handle failures and consider dead-letter queues (DLQ) in your AWS setup.
- Keep handlers fast; offload long-running work if possible.

---

## Status

Mononoke is actively evolving. Expect rapid iteration, breaking changes, and new features as the framework stabilizes.  
Contributions, bug reports, and feedback are highly appreciated!

