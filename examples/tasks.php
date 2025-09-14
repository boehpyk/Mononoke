<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Attributes\Task;
use Kekke\Mononoke\Helpers\Logger;
use Kekke\Mononoke\Service as MononokeService;
use Kekke\Mononoke\Transport\BackgroundTask;

class Service extends MononokeService
{
    #[Http('GET', '/create')]
    public function create()
    {
        $task = new BackgroundTask($this->server);
        $task->dispatch('task_name', ['message' => 'Hello World!']);
        return "OK";
    }

    #[Task(identifier: 'task_name')]
    public function task($data)
    {
        Logger::info("Task handler executed", ['data' => $data]);
    }
}
