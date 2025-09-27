<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Server;

use Swoole\Server;

interface ServerFactory
{
    public function create(Options $options): Server;
    public function extend(Server &$server, Options $options): void;
}
