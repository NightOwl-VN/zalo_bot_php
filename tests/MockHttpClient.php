<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Mock PSR-18 HTTP client for testing - never calls real APIs.
 *
 * Usage:
 *   $mock = MockHttpClient::of(json_encode(['ok' => true]), 200);
 *   $client = new ZaloClient('test-token', httpClient: $mock);
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<callable(RequestInterface): ResponseInterface> */
    private array $handlers = [];

    /** @var list<array{uri: string, method: string}> */
    public array $requests = [];

    private function __construct()
    {
    }

    /**
     * Create a mock that always returns the same response.
     */
    public static function of(string $body = '{}', int $statusCode = 200, array $headers = []): self
    {
        $mock = new self();
        $mock->handlers[] = static fn (RequestInterface $request): ResponseInterface =>
            static::response($body, $statusCode, $headers);
        return $mock;
    }

    /**
     * Create a mock that returns a specific response for each sequential request.
     *
     * @param list<ResponseInterface> $responses
     */
    public static function sequence(array $responses): self
    {
        $mock = new self();
        foreach ($responses as $response) {
            $mock->handlers[] = static fn (RequestInterface $request): ResponseInterface => $response;
        }
        return $mock;
    }

    /**
     * Create a mock with sequential responses or thrown exceptions.
     *
     * @param list<array{0: 'throw'|'response', 1: mixed}> $actions
     */
    public static function sequenceWithFailures(array $actions): self
    {
        $mock = new self();
        foreach ($actions as [$type, $value]) {
            $mock->handlers[] = static function (RequestInterface $request) use ($type, $value): ResponseInterface {
                if ($type === 'throw') {
                    throw $value;
                }
                return $value;
            };
        }
        return $mock;
    }

    /**
     * Create a mock from a handler callable.
     *
     * @param callable(RequestInterface): ResponseInterface $handler
     */
    public static function handler(callable $handler): self
    {
        $mock = new self();
        $mock->handlers[] = $handler;
        return $mock;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'uri' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        if (empty($this->handlers)) {
            throw new \RuntimeException('MockHttpClient: no more handlers registered');
        }

        $handler = $this->handlers[0];
        // Only consume from the front if there are more handlers
        if (count($this->handlers) > 1) {
            array_shift($this->handlers);
        }

        return $handler($request);
    }

    public function getCallCount(): int
    {
        return count($this->requests);
    }

    /**
     * Build a PSR-7 ResponseInterface.
     */
    public static function response(
        string $body = '{}',
        int $statusCode = 200,
        array $headers = [],
    ): ResponseInterface {
        $defaultHeaders = ['Content-Type' => 'application/json'];
        return new \GuzzleHttp\Psr7\Response(
            $statusCode,
            array_merge($defaultHeaders, $headers),
            $body,
        );
    }

    /**
     * Build a mock that throws a Guzzle ConnectException (simulates connection failure).
     */
    public static function networkFailure(string $message = 'Connection refused'): self
    {
        $mock = new self();
        $mock->handlers[] = static function (RequestInterface $request) use ($message): never {
            throw new \GuzzleHttp\Exception\ConnectException($message, $request);
        };
        return $mock;
    }

    /**
     * Build a mock that throws a Guzzle RequestException (simulates client errors).
     *
     * Uses the static create() factory to correctly wire the response object
     * into the exception, rather than passing it as the $code parameter.
     */
    public static function clientError(
        int $statusCode,
        string $body = '{}',
        string $message = 'Client Error',
    ): self {
        $mock = new self();
        $mock->handlers[] = static function (RequestInterface $request) use ($statusCode, $body, $message): never {
            $response = static::response($body, $statusCode);
            throw \GuzzleHttp\Exception\RequestException::create($request, $response);
        };
        return $mock;
    }
}
