<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use FastRoute\RouteCollector;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Aws\SnsSqsInstaller;
use Kekke\Mononoke\Aws\SqsMessageHandler;
use Kekke\Mononoke\Aws\SqsPoller;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Scheduling\ScheduledInvoker;
use Kekke\Mononoke\Scheduling\SchedulerEvaluator;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\SystemClock;
use Kekke\Mononoke\Services\SqsService;
use ReflectionClass;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\Http\Message\Response;
use RuntimeException;

use function FastRoute\simpleDispatcher;

/**
 * Main entrypoint for a Mononoke service
 * Extend this Service class and then use the method `run` to start the service
 */
class Service
{
    protected SqsClient $sqs;
    protected SnsClient $sns;

    /**
     * Starts the service
     * This method will create the HTTP server and SQS poller if needed
     */
    public function run(): void
    {
        $this->setupScheduler();
        $this->setupQueuePoller();
        $this->setupHttpServer();

        Logger::info("Mononoke framework up and running!");
    }

    private function setupHttpServer(): void
    {
        $reflector = new ReflectionClass($this);
        $routes = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(\Kekke\Mononoke\Attributes\Http::class) as $attr) {
                /** @var \Kekke\Mononoke\Attributes\Http $instance */
                $instance = $attr->newInstance();
                $routes[] = [$instance->method->value, $instance->path, [$this, $method->getName()]];
            }
        }

        $dispatcher = simpleDispatcher(function (RouteCollector $r) use ($routes) {
            foreach ($routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });

        // Setup HTTP server
        $server = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($dispatcher) {
            $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

            switch ($routeInfo[0]) {
                case \FastRoute\Dispatcher::NOT_FOUND:
                    return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    $result = call_user_func_array($handler, $vars);

                    if ($result instanceof Response) {
                        return $result;
                    }

                    if (is_array($result)) {
                        return new Response(200, ['Content-Type' => 'application/json'], json_encode($result));
                    }

                    return new Response(200, ['Content-Type' => 'text/plain'], $result);
            }

            return new Response(500, ['Content-Type' => 'text/plain'], 'Unexpected error');
        });

        try {
            $socket = new SocketServer('0.0.0.0:80', [], null);
            $server->listen($socket);
        } catch (RuntimeException $e) {
            Logger::exception("Unable to start http server: {$e->getMessage()}", $e);
            throw new MononokeException("Unable to start http server: {$e->getMessage()}");
        }
    }

    private function setupQueuePoller(): void
    {
        $reflector = new ReflectionClass($this);
        $queueEntries = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(AwsSnsSqs::class) as $attr) {
                /** @var AwsSnsSqs $instance */
                $instance = $attr->newInstance();

                // Setup sns and sqs
                $installer = new SnsSqsInstaller($instance->topicName, $instance->queueName);
                $installer->setup();
                $queueUrl = $installer->getQueueUrl();

                // Setup poller
                $sqsService = new SqsService();
                $poller = new SqsPoller($sqsService->getClient(), $queueUrl);

                // Setup invoker
                $messageHandlerClosure = $method->getClosure($this);
                $handler = new SqsMessageHandler($messageHandlerClosure);

                $queueEntries[] = ['poller' => $poller, 'handler' => $handler];
            }
        }

        Loop::addPeriodicTimer(5, function () use ($queueEntries) {
            foreach ($queueEntries as $queueEntry) {
                $messages = $queueEntry['poller']->poll();

                foreach ($messages as $message) {
                    $queueEntry['handler']->handle($message['Body']);
                    $queueEntry['poller']->delete($message['ReceiptHandle']);
                }
            }
        });
    }

    private function setupScheduler(): void
    {
        $reflector = new ReflectionClass($this);

        $clock = new SystemClock();
        $evaluator = new SchedulerEvaluator($clock);

        $scheduleEntries = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(Schedule::class) as $attr) {
                /** @var Schedule $scheduleMeta */
                $scheduleMeta = $attr->newInstance();

                $state = new ScheduleState();
                $invoker = new ScheduledInvoker($state, $clock);
                $closure = $method->getClosure($this);
                $invoker->setCallable($closure);

                $scheduleEntries[] = [
                    'meta'    => $scheduleMeta,
                    'state'   => $state,
                    'invoker' => $invoker,
                ];
            }
        }

        Loop::addPeriodicTimer(0, function () use ($scheduleEntries, $evaluator) {
            foreach ($scheduleEntries as $entry) {
                if ($evaluator->shouldRun($entry['meta'], $entry['state'])) {
                    $entry['invoker']->invoke();
                }
            }
        });
    }
}
