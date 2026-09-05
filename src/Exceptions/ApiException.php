<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Exceptions;

/**
 * Error thrown when Zalo Bot API returns an error response.
 */
class ApiException extends ZaloBotException
{
    public function __construct(
        string $message,
        ?int $apiErrorCode = null,
        ?int $httpStatus = null,
        mixed $details = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $apiErrorCode, $httpStatus, $details, $previous);
    }
}
