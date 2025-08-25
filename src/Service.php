<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Aws\AwsClientFactory;
use Kekke\Mononoke\Aws\SnsSqsInstaller;
use Kekke\Mononoke\Aws\SqsMessageHandler;
use Kekke\Mononoke\Aws\SqsPoller;
use Kekke\Mononoke\Enums\ClientType;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Reflection\AttributeScanner;
use Kekke\Mononoke\Scheduling\ScheduledInvoker;
use Kekke\Mononoke\Scheduling\SchedulerEvaluator;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\SystemClock;
use Kekke\Mononoke\Server\Http\HttpRouteLoader;
use Kekke\Mononoke\Server\Http\HttpServerFactory;
use Kekke\Mononoke\Server\WebSocket\WebSocketRouteLoader;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\WebSocket\WebSocketAndHttpServerFactory;
use Kekke\Mononoke\Server\WebSocket\WebSocketServerFactory;
use Swoole\Event;
use Swoole\Http\Server;
use Swoole\Timer;

/**
 * Main entrypoint for a Mononoke service
 * Extend this Service class and then use the method `run` to start the service
 */
class Service
{
    protected SqsClient $sqs;
    protected SnsClient $sns;
    private int $port = 80;

    /**
     * Starts the service
     * This method will create the HTTP server and SQS poller if needed
     */
    public function run(): void
    {
        $httpRouteLoader = new HttpRouteLoader();
        $wsRouteLoader = new WebSocketRouteLoader();

        $httpRoutes = $httpRouteLoader->load($this);
        $wsRoutes = $wsRouteLoader->load($this);

        $options = new Options($this->port, $httpRoutes, $wsRoutes);

        $server = null;

        if (count($wsRoutes) > 0 && count($httpRoutes) > 0) {
            $server = (new WebSocketAndHttpServerFactory())->create($options);
        }
        else if (count($wsRoutes) > 0) {
            $server = (new WebSocketServerFactory())->create($options);
        }
        else if (count($httpRoutes) > 0) {
            $server = (new HttpServerFactory())->create($options);
        }

        $this->setupSignalHandler($server);
        $this->setupQueuePoller();
        $this->setupScheduler();

        Logger::info("Mononoke framework up and running!");

        if (!is_null($server)) {
            $server->start();
        } else {
            Event::wait();
        }
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    private function setupSignalHandler(?Server $server): void
    {
        $killCommand = function () use ($server) {
            Logger::info("Stopping service");

            if (!is_null($server)) {
                $server->shutdown();
            }
            Event::exit();

            Logger::info("Terminated service");
        };

        pcntl_signal(SIGINT, $killCommand);
        pcntl_signal(SIGTERM, $killCommand);

        Timer::tick(200, function () {
            pcntl_signal_dispatch();
        });
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

        if (count($queueEntries) > 0) {
            Logger::info("SQS listeners registered", ['number_of_sqs_listeners' => count($queueEntries)]);

            Timer::tick(5000, function () use ($queueEntries) {
                foreach ($queueEntries as $queueEntry) {
                    $messages = $queueEntry['poller']->poll();

                    foreach ($messages as $message) {
                        $queueEntry['handler']->handle($message['Body']);
                        $queueEntry['poller']->delete($message['ReceiptHandle']);
                    }
                }
            });
        }
    }

    private function setupScheduler(): void
    {
        $scanner = new AttributeScanner($this);
        $scheduleMethods = $scanner->getMethodsWithAttribute(Schedule::class);
        $scheduleEntries = [];

        $clock = new SystemClock();
        $evaluator = new SchedulerEvaluator($clock);

        foreach ($scheduleMethods as $entry) {
            foreach ($entry['attributes'] as $attr) {
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

        if (count($scheduleEntries) > 0) {
            Logger::info("Schedulers registered", ['number_of_schedulers' => count($scheduleEntries)]);

            Timer::tick(1, function () use ($scheduleEntries, $evaluator) {
                foreach ($scheduleEntries as $entry) {
                    if ($evaluator->shouldRun($entry['meta'], $entry['state'])) {
                        $entry['invoker']->invoke();
                    }
                }
            });
        }
    }
}
