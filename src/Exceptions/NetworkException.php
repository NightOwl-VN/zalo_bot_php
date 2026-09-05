<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when a network request fails (DNS, connection refused, etc.).
 */
class NetworkException extends ZaloBotException
{
    public function __construct(
        string $message = 'Network error',
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, null, $details, $previous);
    }
}
