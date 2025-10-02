<?php

declare(strict_types=1);

use Kekke\Mononoke\Exceptions\ImmutableConfigIsNotImmutableException;
use Kekke\Mononoke\Exceptions\MononokeException;
use Kekke\Mononoke\Models\ImmutableConfig;
use PHPUnit\Framework\TestCase;

final class InvalidConfig extends ImmutableConfig
{
    public function __construct(public int $notImmutable = 5)
    {
        parent::__construct();
    }
}

final class ValidConfig extends ImmutableConfig
{
    public function __construct(
        public readonly int $foo = 1,
        public readonly string $bar = 'baz'
    ) {
        parent::__construct();
    }
}

final class ImmutableConfigTest extends TestCase
{
    public function testInvalidConfigThrowsException(): void
    {
        $this->expectException(ImmutableConfigIsNotImmutableException::class);
        $this->expectExceptionMessage('Property $notImmutable in InvalidConfig must be promoted and readonly in constructor');

        $config = new InvalidConfig();
    }

    public function testWithOverridesValue(): void
    {
        $config = new ValidConfig(foo: 5, bar: 'baz');
        $newConfig = $config->with('foo', 10);

        $this->assertInstanceOf(ValidConfig::class, $newConfig);
        $this->assertSame(10, $newConfig->foo);
        $this->assertSame('baz', $newConfig->bar);
        $this->assertNotSame($config, $newConfig);
    }

    public function testWithInvalidPropertyThrows(): void
    {
        $this->expectException(MononokeException::class);
        $this->expectExceptionMessage("Property 'nonExistent' does not exist in ValidConfig");
        
        $config = new ValidConfig();
        $config->with('nonExistent', 123);
    }

    public function testValidConfigDoesNotThrow(): void
    {
        $config = new ValidConfig();
        $this->assertInstanceOf(ValidConfig::class, $config);
    }
}
