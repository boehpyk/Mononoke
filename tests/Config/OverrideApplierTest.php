<?php

declare(strict_types=1);

use Kekke\Mononoke\Attributes\Config;
use Kekke\Mononoke\Config\OverrideApplier;
use Kekke\Mononoke\Models\Override;
use PHPUnit\Framework\TestCase;

final class OverrideApplierTest extends TestCase
{
    private OverrideApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new OverrideApplier();
    }

    public function testItDoesNotApplyWhenEnvVarIsNotSet(): void
    {
        $config = new Config();
        $override = new Override('http', 'port', 'MISSING_ENV');

        $this->applier->apply($config, $override);

        $this->assertSame(80, $config->http->port);
    }

    public function testItDoesNotApplyWhenConfigNameDoesNotExist(): void
    {
        putenv('FOO_PORT=9000');
        $config = new Config();
        $override = new Override('doesNotExist', 'port', 'FOO_PORT');

        $this->applier->apply($config, $override);

        $this->assertSame(80, $config->http->port);
    }

    public function testItDoesNotApplyWhenVarNameDoesNotExist(): void
    {
        putenv('HTTP_PORT=9000');
        $config = new Config();
        $override = new Override('http', 'doesNotExist', 'HTTP_PORT');

        $this->applier->apply($config, $override);

        $this->assertSame(80, $config->http->port);
    }

    public function testItCastsAndAppliesIntegerOverride(): void
    {
        putenv('HTTP_PORT=9000');
        $config = new Config();
        $override = new Override('http', 'port', 'HTTP_PORT');

        $this->applier->apply($config, $override);

        $this->assertSame(9000, $config->http->port);
        $this->assertIsInt($config->http->port);
    }
}
