<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Service as MononokeService;
use React\Http\Message\Response;

class Service extends MononokeService
{
    #[Http('GET', '/health')]
    public function status()
    {
        return "OK";
    }

    #[Http('GET', '/json')]
    public function json()
    {
        return ['test' => 'json?'];
    }

    #[Http('GET', '/custom')]
    public function custom()
    {
        return new Response(201, ['Authorization' => 'Bearer YeaH!'], 'Body');
    }
}
