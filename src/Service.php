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
use Kekke\Mononoke\Enums\RuntimeEvent;
use Kekke\Mononoke\Helpers\HookDispatcher;
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
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;
use Throwable;

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
        $hookDispatcher = new HookDispatcher($this);
        $httpRouteLoader = new HttpRouteLoader();
        $wsRouteLoader = new WebSocketRouteLoader();
        $taskRouteLoader = new TaskRouteLoader();

        $httpRoutes = $httpRouteLoader->load($this);
        $wsRoutes = $wsRouteLoader->load($this);
        $taskRoutes = $taskRouteLoader->load($this);

        $options = new Options($httpRoutes, $wsRoutes, $taskRoutes, $this->config);

        $server = $this->setupServer($options, $hookDispatcher);

        $this->setupQueuePoller();
        $this->setupScheduler();
        
        Logger::info("Mononoke framework up and running!");
        
        $hookDispatcher->trigger(RuntimeEvent::OnStart);
        
        if (!is_null($server)) {
            $this->server = &$server;
            $server->start();
        } else {
            $this->setupSignalHandlers($hookDispatcher, $server);
            Event::wait();
        }
    }

    final public function loadConfig(): void
    {
        $configLoader = new ConfigLoader();

        $this->config = $configLoader->load($this);
        $this->config = $configLoader->applyOverrides($this->config);
    }

    final public function getConfig(): Config
    {
        return $this->config;
    }

    private function setupSignalHandlers(HookDispatcher $hookDispatcher, ?Server $server = null): void
    {
        Process::signal(SIGINT, function () use ($server, $hookDispatcher) {
            $hookDispatcher->trigger(RuntimeEvent::OnShutdown);

            if (!is_null($server)) {
                $server->shutdown();
            } else {
                Event::exit();
            }
        });

        Process::signal(SIGTERM, function () use ($server, $hookDispatcher) {
            $hookDispatcher->trigger(RuntimeEvent::OnShutdown);

            if (!is_null($server)) {
                $server->shutdown();
            } else {
                Event::exit();
            }
        });
    }

    private function setupServer(Options $options, HookDispatcher $hookDispatcher): ?Server
    {
        $server = null;

        if (count($options->wsRoutes) > 0) {
            $server = (new WebSocketServerFactory())->create($options);
            Logger::info("Started WebSocket server at port {$options->config->http->port}");
        }

        if (count($options->httpRoutes) > 0) {
            if (is_null($server)) {
                $server = (new HttpServerFactory())->create($options);
            } else {
                (new HttpServerFactory())->extend($server, $options);
            }

            Logger::info("Started HTTP server at port {$this->config->http->port}");
        }

        if (count($options->taskRoutes) > 0) {
            if (is_null($server)) {
                $server = (new TaskServerFactory())->create($options);
            } else {
                (new TaskServerFactory())->extend($server, $options);
            }

            $server->set([Constant::OPTION_TASK_WORKER_NUM => $this->config->mononoke->numberOfTaskWorkers]); // @phpstan-ignore-line
            Logger::info("Created {$this->config->mononoke->numberOfTaskWorkers} task workers");
        }

        if (!is_null($server)) {
            $server->on('Shutdown', function () use ($hookDispatcher) {
                $hookDispatcher->trigger(RuntimeEvent::OnShutdown);
            });
        }

        return $server;
    }

    private function setupQueuePoller(): void
    {
        $scanner = new AttributeScanner($this);
        $queueMethods = $scanner->getMethodsWithAttribute(AwsSnsSqs::class);
        $queueEntries = [];

        foreach ($queueMethods as $entry) {
            foreach ($entry['attributes'] as $attr) {
                // Setup sns and sqs
                $installer = new SnsSqsInstaller($attr->topicName, $attr->queueName, $attr->dlqName);
                $installer->setup(config: $this->config->aws);
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

            Timer::tick($this->config->aws->sqsPollTimeInSeconds * 1000, function () use ($queueEntries) {
                foreach ($queueEntries as $queueEntry) {
                    $messages = $queueEntry['poller']->poll();

                    foreach ($messages as $message) {
                        try {
                            $queueEntry['handler']->handle($message['Body']);
                            $queueEntry['poller']->delete($message['ReceiptHandle']);
                        } catch (Throwable $e) {
                            Logger::exception("Error occured when handling message", $e);
                        }
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
