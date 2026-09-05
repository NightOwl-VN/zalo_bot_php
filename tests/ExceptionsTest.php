<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\NetworkException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\TimeoutException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Exceptions\WebhookException;
use ZaloBot\Sdk\Exceptions\ZaloBotException;

/**
 * P1.13: All exception constructors must follow the same signature order:
 *   __construct(string $message, ...typeSpecific, mixed $details, ?Throwable $previous)
 * so $previous is ALWAYS the last argument, $details second-to-last.
 */
final class ExceptionsTest extends TestCase
{
    public function testPreviousExceptionIsLastArgumentEverywhere(): void
    {
        $root = new \RuntimeException('root');

        // ApiException(message, apiErrorCode, httpStatus, details, previous)
        $api = new ApiException('m', 1200, 404, ['x' => 1], $root);
        $this->assertSame('m', $api->getMessage());
        $this->assertSame(1200, $api->getApiErrorCode());
        $this->assertSame(404, $api->getHttpStatus());
        $this->assertSame(['x' => 1], $api->getDetails());
        $this->assertSame($root, $api->getPrevious());

        // AuthException(message, httpStatus, details, previous)
        $auth = new AuthException('m', 401, ['x' => 1], $root);
        $this->assertSame(401, $auth->getHttpStatus());
        $this->assertSame($root, $auth->getPrevious());

        // NetworkException(message, details, previous)
        $net = new NetworkException('m', ['x' => 1], $root);
        $this->assertSame($root, $net->getPrevious());

        // RateLimitException(message, httpStatus, retryAfter, details, previous)
        $rate = new RateLimitException('m', 429, 7, ['x' => 1], $root);
        $this->assertSame(7, $rate->getRetryAfter());
        $this->assertSame($root, $rate->getPrevious());

        // TimeoutException(message, details, previous)
        $timeout = new TimeoutException('m', ['x' => 1], $root);
        $this->assertSame($root, $timeout->getPrevious());

        // ValidationException(message, field, details, previous)
        $validation = new ValidationException('m', 'chatId', ['x' => 1], $root);
        $this->assertSame('chatId', $validation->getField());
        $this->assertSame($root, $validation->getPrevious());

        // WebhookException(message, httpStatus, details, previous)
        $webhook = new WebhookException('m', 403, ['x' => 1], $root);
        $this->assertSame(403, $webhook->getHttpStatus());
        $this->assertSame($root, $webhook->getPrevious());
    }

    public function testAllExtendZaloBotException(): void
    {
        foreach ([ApiException::class, AuthException::class, NetworkException::class, RateLimitException::class, TimeoutException::class, ValidationException::class, WebhookException::class] as $class) {
            $this->assertTrue(is_subclass_of($class, ZaloBotException::class), "{$class} must extend ZaloBotException");
        }
    }

    public function testDefaultMessages(): void
    {
        $this->assertSame('Bot token is invalid or expired', (new AuthException())->getMessage());
        $this->assertSame('Network error', (new NetworkException())->getMessage());
        $this->assertSame('Rate limit exceeded', (new RateLimitException())->getMessage());
        $this->assertSame('Request timed out', (new TimeoutException())->getMessage());
        $this->assertSame('Invalid webhook secret token', (new WebhookException())->getMessage());
    }

    public function testRateLimitDefaultsTo429(): void
    {
        $this->assertSame(429, (new RateLimitException())->getHttpStatus());
    }

    public function testWebhookDefaultsTo403(): void
    {
        $this->assertSame(403, (new WebhookException())->getHttpStatus());
    }
}
