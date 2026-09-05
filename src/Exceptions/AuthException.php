<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when bot token is invalid, missing, or expired.
 */
class AuthException extends ZaloBotException
{
    public function __construct(
        string $message = 'Bot token is invalid or expired',
        ?int $httpStatus = null,
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, $httpStatus, $details, $previous);
    }
}
