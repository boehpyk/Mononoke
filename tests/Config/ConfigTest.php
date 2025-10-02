<?php

declare(strict_types=1);

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Models\AwsConfig;
use Kekke\Mononoke\Models\HttpConfig;
use Kekke\Mononoke\Models\MononokeConfig;
use Kekke\Mononoke\Service as MononokeService;
use PHPUnit\Framework\TestCase;

#[Config(
    mononoke: new MononokeConfig(numberOfTaskWorkers: 5),
    aws: new AwsConfig(sqsPollTimeInSeconds: 10),
    http: new HttpConfig(port: 8080),
)]
class TestService extends MononokeService {}

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('HTTP_PORT');
        putenv('TASK_WORKERS');
        putenv('SQS_POLL_TIME');
    }

    public function testDefaultConfigFromAttributes(): void
    {
        $service = new TestService();
        $service->loadConfig();
        $config = $service->getConfig();

        $this->assertSame(5, $config->mononoke->numberOfTaskWorkers);
        $this->assertSame(10, $config->aws->sqsPollTimeInSeconds);
        $this->assertSame(8080, $config->http->port);
    }

    public function testDlqOverrideFromEnv(): void
    {
        putenv('SQS_DLQ_MAX_RETRIES=5');
        $service = new TestService();
        $service->loadConfig();
        $config = $service->getConfig();

        $this->assertSame(5, $config->aws->dlqMaxRetryCount);
    }

    public function testHttpPortOverrideFromEnv(): void
    {
        putenv('HTTP_PORT=9090');

        $service = new TestService();
        $service->loadConfig();
        $config = $service->getConfig();

        $this->assertSame(9090, $config->http->port);
    }

    public function testMultipleOverrides(): void
    {
        putenv('HTTP_PORT=9090');
        putenv('TASK_WORKERS=12');
        putenv('SQS_POLL_TIME=30');

        $service = new TestService();
        $service->loadConfig();
        $config = $service->getConfig();

        $this->assertSame(12, $config->mononoke->numberOfTaskWorkers);
        $this->assertSame(30, $config->aws->sqsPollTimeInSeconds);
        $this->assertSame(9090, $config->http->port);
    }
}
