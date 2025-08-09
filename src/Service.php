<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use FastRoute\RouteCollector;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Helpers\Logger;
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
    public function run()
    {
        $region = getenv('AWS_REGION') ?: 'us-east-1';
        $endpoint = getenv('AWS_ENDPOINT') ?: 'http://localhost:4566';
        $reflector = new ReflectionClass($this);
        $this->sqs = new SqsClient([
            'region' => $region,
            'version' => 'latest',
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
            ]
        ]);

        // Setup scheduler
        $clock = new SystemClock();
        $evaluator = new SchedulerEvaluator($clock);

        $scheduleEntries = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(Schedule::class) as $attr) {
                /** @var Schedule $scheduleMeta */
                $scheduleMeta = $attr->newInstance();

                $state = new ScheduleState();
                $invoker = new ScheduledInvoker($state, $clock);
                $invoker->setCallable([$this, $method->getName()]);

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

        // Setup queue map
        $queueMap = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(AwsSnsSqs::class) as $attr) {
                /** @var AwsSnsSqs $instance */
                $instance = $attr->newInstance();
                $instance->setup();
                $queueMap[$instance->queueUrl] = $method->getName();
            }
        }

        Loop::addPeriodicTimer(5, function () use ($queueMap) {
            foreach ($queueMap as $queueUrl => $methodName) {
                try {
                    Logger::info("Polling from SQS", ['method' => $methodName, 'queueArn' => $queueUrl]);
                    $messageHandlerClosure = \Closure::fromCallable([$this, $methodName]);

                    /** @var \Aws\Result $result */
                    $result = $this->sqs->receiveMessage([
                        'QueueUrl' => $queueUrl,
                        'MaxNumberOfMessages' => 5,
                        'WaitTimeSeconds' => 0
                    ]);

                    if (!empty($result['Messages'])) {
                        foreach ($result['Messages'] as $message) {
                            $body = json_decode($message['Body'], true);

                            Logger::info("Triggering closure", ['body' => $body]);
                            $messageHandlerClosure($body['Message']);

                            $this->sqs->deleteMessage([
                                'QueueUrl' => $queueUrl,
                                'ReceiptHandle' => $message['ReceiptHandle']
                            ]);
                        }
                    }
                } catch (AwsException $e) {
                    Logger::exception(message: "Error polling queue", exception: $e);
                }
            }
        });

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

        Logger::info("Mononoke framework up and running!");
    }
}
