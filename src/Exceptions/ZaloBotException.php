<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Zalo Bot SDK errors.
 */
class ZaloBotException extends Exception
{
    public function __construct(
        string $message = '',
        protected ?int $apiErrorCode = null,
        protected ?int $httpStatus = null,
        protected mixed $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus ?? $apiErrorCode ?? 0, $previous);
    }

    public function getApiErrorCode(): ?int
    {
        return $this->apiErrorCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }
}
