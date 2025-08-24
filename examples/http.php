<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Response as Psr7Response;
use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Service as MononokeService;

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
        return new Psr7Response(201, ['Authorization' => 'Bearer XXX'], "Body");
    }
}
