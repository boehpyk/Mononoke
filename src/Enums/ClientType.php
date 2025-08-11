<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

/**
 * Enum with aws client types
 */
enum ClientType
{
    case SNS;
    case SQS;
}
