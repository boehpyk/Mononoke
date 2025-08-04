<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Enums;

/**
 * Enum with valid http methods
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
}