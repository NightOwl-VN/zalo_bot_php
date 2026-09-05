<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when webhook secret token verification fails.
 */
class WebhookException extends ZaloBotException
{
    public function __construct(
        string $message = 'Invalid webhook secret token',
        ?int $httpStatus = null,
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, $httpStatus ?? 403, $details, $previous);
    }
}
