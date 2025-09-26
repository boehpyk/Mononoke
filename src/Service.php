<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Aws\AwsClientFactory;
use Kekke\Mononoke\Aws\SnsSqsInstaller;
use Kekke\Mononoke\Aws\SqsMessageHandler;
use Kekke\Mononoke\Aws\SqsPoller;
use Kekke\Mononoke\Config\ConfigLoader;
use Kekke\Mononoke\Enums\ClientType;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Reflection\AttributeScanner;
use Kekke\Mononoke\Scheduling\ScheduledInvoker;
use Kekke\Mononoke\Scheduling\SchedulerEvaluator;
use Kekke\Mononoke\Scheduling\ScheduleState;
use Kekke\Mononoke\Scheduling\SystemClock;
use Kekke\Mononoke\Server\Http\HttpRouteLoader;
use Kekke\Mononoke\Server\Http\HttpServerFactory;
use Kekke\Mononoke\Server\Task\TaskServerFactory;
use Kekke\Mononoke\Server\WebSocket\WebSocketRouteLoader;
use Kekke\Mononoke\Server\Options;
use Kekke\Mononoke\Server\Task\TaskRouteLoader;
use Kekke\Mononoke\Server\WebSocket\WebSocketServerFactory;
use Swoole\Constant;
use Swoole\Event;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;

/**
 * Main entrypoint for a Mononoke service
 * Extend this Service class and then use the method `run` to start the service
 */
class Service
{
    protected SqsClient $sqs;
    protected SnsClient $sns;
    protected Server $server;
    protected Config $config;

    /**
     * Starts the service
     * This method will create the HTTP server and SQS poller if needed
     */
    final public function run(): void
    {
        $httpRouteLoader = new HttpRouteLoader();
        $wsRouteLoader = new WebSocketRouteLoader();
        $taskRouteLoader = new TaskRouteLoader();

        $httpRoutes = $httpRouteLoader->load($this);
        $wsRoutes = $wsRouteLoader->load($this);
        $taskRoutes = $taskRouteLoader->load($this);

        $server = null;

        if (count($wsRoutes) > 0) {
            $server = new WebSocketServer("0.0.0.0", $this->config->httpConfig->port);
            Logger::info("Started WebSocket server at port {$this->config->httpConfig->port}");
            $options = new Options($server, $httpRoutes, $wsRoutes, $taskRoutes);
            (new WebSocketServerFactory())->create($options);
        }

        if (count($httpRoutes) > 0) {
            if (is_null($server)) {
                $server = new HttpServer("0.0.0.0", $this->config->httpConfig->port);
                Logger::info("Started HTTP server at port {$this->config->httpConfig->port}");
            }
            $options = new Options($server, $httpRoutes, $wsRoutes, $taskRoutes);
            (new HttpServerFactory())->create($options);
        }

        if (count($taskRoutes) > 0) {
            if (is_null($server)) {
                $server = new Server("0.0.0.0", $this->config->httpConfig->port);
            }
            $options = new Options($server, $httpRoutes, $wsRoutes, $taskRoutes);
            (new TaskServerFactory())->create($options);
            $server->set([Constant::OPTION_TASK_WORKER_NUM => $this->config->mononokeConfig->numberOfTaskWorkers]); // @phpstan-ignore-line
            Logger::info("Created {$this->config->mononokeConfig->numberOfTaskWorkers} task workers");
        }

        $this->setupQueuePoller();
        $this->setupScheduler();

        Logger::info("Mononoke framework up and running!");

        if (!is_null($server)) {
            $this->server = &$server;
            $server->start();
        } else {
            Event::wait();
        }
    }

    final public function loadConfig(): void
    {
        $configLoader = new ConfigLoader();

        $this->config = $configLoader->load($this);

        $this->config = $this->applyOverrides($this->config);
    }

    final public function getConfig(): Config
    {
        return $this->config;
    }

    private function applyOverrides(Config $config): Config
    {
        if ($port = getenv('HTTP_PORT')) {
            $config->httpConfig->port = (int)$port;
        }

        if ($workers = getenv('TASK_WORKERS')) {
            $config->mononokeConfig->numberOfTaskWorkers = (int)$workers;
        }

        if ($sqsTime = getenv('SQS_POLL_TIME')) {
            $config->awsConfig->sqsPollTimeInSeconds = (int)$sqsTime;
        }

        return $config;
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

            Timer::tick($this->config->awsConfig->sqsPollTimeInSeconds * 1000, function () use ($queueEntries) {
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
