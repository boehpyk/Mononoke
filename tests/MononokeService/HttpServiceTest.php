<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Tests\Http;

use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Service;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\Message\Response;
use function React\Async\await;


class DummyService extends Service
{
    #[Http('GET', '/health')] // @phpstan-ignore-line
    public function status()
    {
        return "OK";
    }

    #[Http('GET', '/json')] // @phpstan-ignore-line
    public function json()
    {
        return ['test' => 'json?'];
    }

    #[Http('GET', '/custom')] // @phpstan-ignore-line
    public function custom()
    {
        return new Response(201, ['Authorization' => 'Bearer XXX'], 'Body');
    }
}

class HttpServiceTest extends TestCase
{
    private DummyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DummyService();
        $this->service->setPort(8888);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Loop::stop();
    }

    public function testHttpEndpointsRespondCorrectly(): void
    {
        $this->service->run();

        $browser = new Browser();

        $health = await($browser->get('http://127.0.0.1:8888/health'));
        $this->assertSame(200, $health->getStatusCode());
        $this->assertSame('OK', (string) $health->getBody());

        $json = await($browser->get('http://127.0.0.1:8888/json'));
        $this->assertSame(200, $json->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"test":"json?"}', (string) $json->getBody());

        $custom = await($browser->get('http://127.0.0.1:8888/custom'));
        $this->assertSame(201, $custom->getStatusCode());
        $this->assertSame('Bearer XXX', $custom->getHeaderLine('Authorization'));
        $this->assertSame('Body', (string) $custom->getBody());

        Loop::stop();
    }
}