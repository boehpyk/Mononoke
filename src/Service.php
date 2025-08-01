<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use FastRoute\RouteCollector;
use Kekke\Mononoke\Attributes\AwsSnsSqs;
use ReflectionClass;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\Http\Message\Response;

use function FastRoute\simpleDispatcher;

class Service
{
    protected SqsClient $sqs;
    protected SnsClient $sns;

    public function run()
    {
        $region = getenv('AWS_REGION') ?: 'us-east-1';
        $endpoint = getenv('AWS_ENDPOINT') ?: 'http://localhost:4566';

        $this->sqs = new SqsClient([
            'region' => $region,
            'version' => 'latest',
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID') ?: 'test',
                'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: 'test',
            ]
        ]);

        // Setup queue map
        $reflector = new ReflectionClass($this);
        $queueMap = [];

        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes(AwsSnsSqs::class) as $attr) {
                /** @var AwsSnsSqs $instance */
                $instance = $attr->newInstance();
                $instance->setup();
                $queueMap[$instance->queueUrl] = $method->getName();
            }
        }

        echo "Finished creating queueMap\n";
        print_r($queueMap);

        Loop::addPeriodicTimer(5, function () use ($queueMap) {
            foreach ($queueMap as $queueUrl => $methodName) {
                try {
                    echo "Polling $methodName\nQueueUrl: $queueUrl\n";
                    $result = $this->sqs->receiveMessage([
                        'QueueUrl' => $queueUrl,
                        'MaxNumberOfMessages' => 5,
                        'WaitTimeSeconds' => 1
                    ]);

                    if (!empty($result['Messages'])) {
                        foreach ($result['Messages'] as $message) {
                            $body = json_decode($message['Body'], true);

                            // Dispatch message to method
                            $this->{$methodName}($body['Message']);

                            // Delete message
                            $this->sqs->deleteMessage([
                                'QueueUrl' => $queueUrl,
                                'ReceiptHandle' => $message['ReceiptHandle']
                            ]);
                        }
                    }
                } catch (AwsException $e) {
                    echo "Error polling queue: " . $e->getAwsErrorMessage() . "\n";
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

        $socket = new SocketServer('0.0.0.0:80', [], null);
        $server->listen($socket);

        echo "HTTP server running at http://localhost\n";
    }
}
