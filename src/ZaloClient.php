<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\NetworkException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\TimeoutException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Exceptions\ZaloBotException;

class ZaloClient
{
    public const API_BASE_URL = 'https://bot-api.zaloplatforms.com';

    private ClientInterface $httpClient;

    /** HTTP status codes eligible for automatic retry. */
    private const RETRYABLE_STATUS = [408, 429, 502, 503, 504];

    public function __construct(
        private string $botToken,
        private int $timeout = 30000,
        private int $maxRetries = 3,
        private string $baseURL = self::API_BASE_URL,
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => $this->timeout / 1000,
            'http_errors' => false,
        ]);
    }

    public function getBotToken(): string
    {
        return $this->botToken;
    }

    public function updateBotToken(string $newToken): void
    {
        if (trim($newToken) === '') {
            throw new ValidationException('botToken must be a non-empty string', 'botToken');
        }
        $this->botToken = trim($newToken);
    }

    public function getRequestBaseUrl(): string
    {
        return rtrim($this->baseURL, '/') . '/bot' . $this->botToken;
    }

    /**
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    private function request(string $method, string $apiMethod, array $data = [], array $options = []): mixed
    {
        $uri = $this->getRequestBaseUrl() . '/' . ltrim($apiMethod, '/');
        $payload = array_merge($data, $options);

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ];

                if (strtoupper($method) === 'GET') {
                    if ($payload !== []) {
                        $uri .= '?' . http_build_query($payload);
                    }
                    $request = new Request('GET', $uri, $headers);
                } else {
                    $body = json_encode($payload, JSON_THROW_ON_ERROR);
                    $request = new Request('POST', $uri, $headers, $body);
                }

                $response = $this->httpClient->sendRequest($request);
                $status = $response->getStatusCode();

                // Retryable status codes — sleep and loop before decoding
                if (in_array($status, self::RETRYABLE_STATUS, true) && $attempt < $this->maxRetries) {
                    $this->sleepBeforeRetry($attempt, $response->getHeaderLine('Retry-After'));
                    continue;
                }

                return $this->decodeResponse($response);
            } catch (ZaloBotException $e) {
                throw $e;
            } catch (ClientExceptionInterface $e) {
                if ($this->shouldRetryException($e) && $attempt < $this->maxRetries) {
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }
                if ($this->isConnectException($e) && str_contains(strtolower($e->getMessage()), 'timed out')) {
                    throw new TimeoutException('Request timed out: ' . $e->getMessage(), null, $e);
                }
                throw new NetworkException('Network request failed: ' . $e->getMessage(), null, $e);
            } catch (\JsonException $e) {
                throw new ApiException('Unable to encode request payload', -1, null, null, $e);
            } catch (\Throwable $e) {
                throw new NetworkException('Request failed: ' . $e->getMessage(), null, $e);
            }
        }

        throw new NetworkException('Request failed after maximum retries');
    }

    /**
     * Decode a PSR-7 response, throwing typed exceptions on errors.
     *
     * @throws ApiException|RateLimitException|AuthException
     */
    private function decodeResponse(ResponseInterface $response): mixed
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];

        // Auth errors
        if ($status === 401 || $status === 403) {
            throw new AuthException(
                $body['description'] ?? 'Invalid or expired bot token',
                $status,
                $body,
            );
        }

        // Rate limit (exhausted retries)
        if ($status === 429) {
            $retryAfter = $this->parseRetryAfterHeader($response->getHeaderLine('Retry-After'));
            throw new RateLimitException(
                $body['description'] ?? 'Rate limit exceeded',
                $status,
                $retryAfter,
                $body,
            );
        }

        // HTTP client/server errors
        if ($status >= 400) {
            throw new ApiException(
                $body['description'] ?? ('HTTP error ' . $status),
                $body['error_code'] ?? -1,
                $status,
                $body,
            );
        }

        // API-level error (200 with ok:false)
        if (($body['ok'] ?? null) === false) {
            throw new ApiException(
                $body['description'] ?? 'Unknown error',
                $body['error_code'] ?? -1,
                $status,
                $body,
            );
        }

        return $body !== [] ? $body : $raw;
    }

    private function shouldRetryException(ClientExceptionInterface $e): bool
    {
        if ($this->isConnectException($e)) {
            return !str_contains(strtolower($e->getMessage()), 'timed out');
        }
        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            if ($response !== null) {
                return in_array($response->getStatusCode(), self::RETRYABLE_STATUS, true);
            }
        }
        return false;
    }

    private function isConnectException(ClientExceptionInterface $e): bool
    {
        return $e instanceof \GuzzleHttp\Exception\ConnectException;
    }

    private function parseRetryAfterHeader(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function sleepBeforeRetry(int $attempt, string $retryAfter = ''): void
    {
        $serverDelay = $this->parseRetryAfterHeader($retryAfter);
        $delayMs = $serverDelay !== null
            ? $serverDelay * 1000
            : min(1000 * (2 ** $attempt) + random_int(0, 250), 30000);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    public function get(string $method, array $params = []): mixed
    {
        return $this->request('GET', $method, $params);
    }

    /**
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    public function post(string $method, array $data = []): mixed
    {
        return $this->request('POST', $method, $data);
    }

    /**
     * Upload a file via multipart/form-data.
     *
     * @param array<string, \CURLFile|mixed> $formData
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    public function upload(string $method, array $formData): mixed
    {
        $boundary = '----ZaloBotBoundary' . bin2hex(random_bytes(8));
        $uri = $this->getRequestBaseUrl() . '/' . ltrim($method, '/');

        $parts = [];
        foreach ($formData as $name => $contents) {
            $parts[] = $this->buildMultipartPart($name, $contents, $boundary);
        }

        $body = implode('', $parts) . "--{$boundary}--\r\n";

        $request = (new Request('POST', $uri))
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->withBody(Utils::streamFor($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Upload failed: ' . $e->getMessage(), null, $e);
        }

        return $this->decodeResponse($response);
    }

    private function buildMultipartPart(string $name, mixed $contents, string $boundary): string
    {
        if ($contents instanceof \CURLFile) {
            $filename = $contents->getPostFilename();
            $mime = mime_content_type($contents->getFilename()) ?: 'application/octet-stream';
            $fileData = file_get_contents($contents->getFilename());
            return "--{$boundary}\r\n"
                . "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n"
                . "Content-Type: {$mime}\r\n\r\n"
                . $fileData . "\r\n";
        }

        return "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n"
            . (string) $contents . "\r\n";
    }

    /**
     * Download a file via PSR-18 (used by MediaModule).
     *
     * @throws NetworkException|TimeoutException
     */
    public function download(string $url): ResponseInterface
    {
        $request = new Request('GET', $url, ['Accept' => '*/*']);
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            if ($this->isConnectException($e) && str_contains(strtolower($e->getMessage()), 'timed out')) {
                throw new TimeoutException('Download timed out: ' . $e->getMessage(), null, $e);
            }
            throw new NetworkException('Download failed: ' . $e->getMessage(), null, $e);
        }
    }
}
