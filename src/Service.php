<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use FastRoute\RouteCollector;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Aws\AwsClientFactory;
use Kekke\Mononoke\Aws\SnsSqsInstaller;
use Kekke\Mononoke\Aws\SqsMessageHandler;
use Kekke\Mononoke\Aws\SqsPoller;
use Kekke\Mononoke\Enums\ClientType;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Reflection\AttributeScanner;
use Kekke\Mononoke\Scheduling\ScheduledInvoker;
use Kekke\Mononoke\Scheduling\SchedulerEvaluator;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\SystemClock;
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
        $socket = $this->setupHttpServer();

        $killCommand = function () use ($socket) {
            $socket->close();
            Loop::stop();
        };

        Loop::addSignal(SIGINT, $killCommand);
        Loop::addSignal(SIGTERM, $killCommand);

        Logger::info("Mononoke framework up and running!");
    }

    private function setupHttpServer(): SocketServer
    {
        $scanner = new AttributeScanner($this);
        $httpMethods = $scanner->getMethodsWithAttribute(\Kekke\Mononoke\Attributes\Http::class);

        $routes = [];
        foreach ($httpMethods as $entry) {
            foreach ($entry['attributes'] as $httpAttr) {
                $routes[] = [
                    $httpAttr->method->value,
                    $httpAttr->path,
                    [$this, $entry['method']->getName()]
                ];
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
                        return new Response(200, ['Content-Type' => 'application/json'], json_encode($result, JSON_THROW_ON_ERROR));
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

        return $socket;
    }

    private function setupQueuePoller(): void
    {
        $scanner = new AttributeScanner($this);
        $queueMethods = $scanner->getMethodsWithAttribute(AwsSnsSqs::class);
        $queueEntries = [];

        foreach ($queueMethods as $entry) {
            foreach ($entry['attributes'] as $attr) {
                // Setup sns and sqs
                $installer = new SnsSqsInstaller($attr->topicName, $attr->queueName);
                $installer->setup();
                $queueUrl = $installer->getQueueUrl();

                // Setup poller
                
                /** @var SqsClient $sqsClient */
                $sqsClient = AwsClientFactory::create(ClientType::SQS);
                $poller = new SqsPoller($sqsClient, $queueUrl);

                // Setup invoker
                $messageHandlerClosure = $entry['method']->getClosure($this);
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
        $scanner = new AttributeScanner($this);
        $scheduleMethods = $scanner->getMethodsWithAttribute(Schedule::class);
        $scheduleEntries = [];

        $clock = new SystemClock();
        $evaluator = new SchedulerEvaluator($clock);

        foreach($scheduleMethods as $entry) {
            foreach($entry['attributes'] as $attr) {
                $state = new ScheduleState();
                $invoker = new ScheduledInvoker($state, $clock);
                $closure = $entry['method']->getClosure($this);
                $invoker->setCallable($closure);

                $scheduleEntries[] = [
                    'meta' => $attr,
                    'state' => $state,
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
