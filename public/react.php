<?php

require 'vendor/autoload.php';

use React\EventLoop\Factory;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Timer;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

$loop = Factory::create();

echo "OK";

// Create SQS client
$sqs = new SqsClient([
    'region' => 'us-east-1',
    'version' => 'latest',
    'endpoint' => 'http://localhost:4566', // localstack
    'credentials' => [
        'key' => 'test',
        'secret' => 'test',
    ]
]);

$queueUrl = 'http://localhost:4566/000000000000/my-queue'; // Adjust for your setup

// SQS polling (every 5 seconds)
$loop->addPeriodicTimer(5, function () use ($sqs, $queueUrl) {
    echo "Polling SQS...\n";
    try {
        $result = $sqs->receiveMessage([
            'QueueUrl' => $queueUrl,
            'MaxNumberOfMessages' => 5,
            'WaitTimeSeconds' => 1,
        ]);

        if (!empty($result['Messages'])) {
            foreach ($result['Messages'] as $message) {
                echo "Message received: {$message['Body']}\n";

                // Handle the message...

                $sqs->deleteMessage([
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle']
                ]);
            }
        }
    } catch (AwsException $e) {
        echo "SQS Polling error: " . $e->getMessage() . "\n";
    }
});

// HTTP server
$server = new HttpServer(function (ServerRequestInterface $request) {
    return \React\Http\Message\Response::plaintext("Hello from ReactPHP + SQS Poller!");
});

$socket = new SocketServer('0.0.0.0:8080', [], $loop);
$server->listen($socket);

echo "HTTP server running on http://localhost:8080\n";

$loop->run();