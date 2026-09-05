<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when rate limit is exceeded (HTTP 429).
 */
class RateLimitException extends ZaloBotException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $httpStatus = null,
        protected ?int $retryAfter = null,
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, $httpStatus ?? 429, $details, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
