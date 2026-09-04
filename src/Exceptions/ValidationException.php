<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when input validation fails.
 */
class ValidationException extends ZaloBotException
{
    public function __construct(
        string $message,
        protected ?string $field = null,
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, null, null, $details, $previous);
    }

    public function getField(): ?string
    {
        return $this->field;
    }
}
