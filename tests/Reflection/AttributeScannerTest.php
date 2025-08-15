<?php

declare(strict_types=1);

namespace Tests\Reflection;

use Kekke\Mononoke\Attributes\Http;
use Kekke\Mononoke\Attributes\Schedule;
use Kekke\Mononoke\Enums\HttpMethod;
use Kekke\Mononoke\Enums\Scheduler;
use Kekke\Mononoke\Reflection\AttributeScanner;
use PHPUnit\Framework\TestCase;

final class AttributeScannerTest extends TestCase
{
    public function testThatWeCanGetAttribute(): void
    {
        $scanner = new AttributeScanner(new class {
            #[Http(HttpMethod::GET, '/')]
            public function test(): void
            {
                return;
            }
        });

        $attributes = $scanner->getMethodsWithAttribute(Http::class);

        $this->assertEquals(1, count($attributes));
    }

    public function testThatHavingWeDoNotReturnEmptyArrays(): void
    {
        $scanner = new AttributeScanner(new class {
            #[Http(HttpMethod::GET, '/')]
            public function test(): void
            {
                return;
            }
        });

        $scheduleAttributes = $scanner->getMethodsWithAttribute(Schedule::class);

        $this->assertEquals(0, count($scheduleAttributes));
    }

    public function testThatHavingMethodsWithoutAttributesDontGetReturned(): void
    {
        $scanner = new AttributeScanner(new class {
            #[Schedule(Scheduler::EveryMinute)]
            public function test(): void
            {
                return;
            }

            public function methodWithoutAttribute(): void
            {
                return;
            }
        });

        $scheduleAttributes = $scanner->getMethodsWithAttribute(Schedule::class);

        $this->assertEquals(1, count($scheduleAttributes));
    }

    public function testThatWeCanHaveMultipleAttributes(): void
    {
        $scanner = new AttributeScanner(new class {
            #[Schedule(Scheduler::EveryMinute)]
            #[Http(HttpMethod::GET, '/')]
            public function test(): void
            {
                return;
            }

            #[Http(HttpMethod::GET, '/yes')]
            public function test2(): void
            {
                return;
            }
        });

        $httpAttributes = $scanner->getMethodsWithAttribute(Http::class);
        $scheduleAttributes = $scanner->getMethodsWithAttribute(Schedule::class);

        $this->assertEquals(3, count([...$httpAttributes, ...$scheduleAttributes]));
    }
}
