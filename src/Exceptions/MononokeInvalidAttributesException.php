<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Exceptions;

use Throwable;

class MononokeInvalidAttributesException extends MononokeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
