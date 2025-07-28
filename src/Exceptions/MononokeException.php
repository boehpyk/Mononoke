<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Exceptions;

use Exception;
use Throwable;

class MononokeException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
