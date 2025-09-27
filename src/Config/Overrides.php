<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Config;

use Kekke\Mononoke\Models\Override;

/**
 * @implements \IteratorAggregate<Override>
 */
class Overrides implements \IteratorAggregate
{
    /** @var Override[] */
    private array $items;

    public function __construct()
    {
        $this->items = [
            new Override('mononoke', 'numberOfTaskWorkers', 'TASK_WORKERS'),
            new Override('http', 'port', 'HTTP_PORT'),
            new Override('aws', 'sqsPollTimeInSeconds', 'SQS_POLL_TIME'),
        ];
    }

    /**
     * @return \Traversable<Override>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
