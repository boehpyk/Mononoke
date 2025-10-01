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
            new Override(configName: 'mononoke', varName: 'numberOfTaskWorkers', envVar: 'TASK_WORKERS'),
            new Override(configName: 'http', varName: 'port', envVar: 'HTTP_PORT'),
            new Override(configName: 'aws', varName: 'sqsPollTimeInSeconds', envVar: 'SQS_POLL_TIME'),
            new Override(configName: 'aws', varName: 'dlqMaxRetryCount', envVar: 'SQS_DLQ_MAX_RETRIES')
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
