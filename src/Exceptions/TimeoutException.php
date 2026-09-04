<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when a request times out.
 */
class TimeoutException extends ZaloBotException
{
    public function __construct(
        string $message = 'Request timed out',
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, null, $details, $previous);
    }
}
