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
        /** Test seam: set to 0 to disable sleeping; null uses normal backoff. */
        private ?int $retryDelayMs = null,
        /** Mutating POST/upload retries are opt-in for idempotency safety. */
        private bool $retryMutations = true,
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
        $baseUri = $this->getRequestBaseUrl() . '/' . ltrim($apiMethod, '/');
        $payload = array_merge($data, $options);
        $canRetry = strtoupper($method) === 'GET' || $this->retryMutations;

        for ($attempt = 0; $attempt <= ($canRetry ? $this->maxRetries : 0); $attempt++) {
            try {
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ];

                if (strtoupper($method) === 'GET') {
                    $uri = $baseUri;
                    if ($payload !== []) {
                        $uri .= '?' . http_build_query($payload);
                    }
                    $request = new Request('GET', $uri, $headers);
                } else {
                    $uri = $baseUri;
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
                if ($this->isTimeoutException($e)) {
                    throw new TimeoutException('Request timed out: ' . $e->getMessage(), null, $e);
                }
                throw new NetworkException('Network request failed: ' . $e->getMessage(), null, $e);
            } catch (\JsonException $e) {
                throw new ApiException('Unable to encode request payload', -1, null, null, $e);
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
        // Decode non-throwing on purpose: a 2xx response with a non-JSON body is
        // returned as the raw string (documented public behaviour). $body stays
        // an empty array for error mapping when decoding fails; JsonException is
        // only thrown by json_encode() during request building, where it is
        // translated semantically into an ApiException.
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
            return !$this->isTimeoutException($e);
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

    private function isTimeoutException(ClientExceptionInterface $e): bool
    {
        if (!$e instanceof \GuzzleHttp\Exception\ConnectException
            && !$e instanceof \GuzzleHttp\Exception\RequestException) {
            return false;
        }
        $context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];
        if (($context['errno'] ?? null) === 28 || ($context['timed_out'] ?? false) === true) {
            return true;
        }
        // Narrow compatibility fallback for handlers that omit cURL context.
        return preg_match('/\b(timeout|timed out)\b/i', $e->getMessage()) === 1;
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
        if ($this->retryDelayMs === 0) {
            return;
        }
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
        $uri = $this->getRequestBaseUrl() . '/' . ltrim($method, '/');
        $elements = [];
        $handles = [];

        try {
            foreach ($formData as $name => $contents) {
                $elements[] = $this->buildMultipartElement($name, $contents, $handles);
            }

            $multipart = new \GuzzleHttp\Psr7\MultipartStream($elements);
            $request = (new Request('POST', $uri))
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $multipart->getBoundary())
                ->withBody($multipart);

            $response = $this->httpClient->sendRequest($request);
            return $this->decodeResponse($response);
        } catch (ClientExceptionInterface $e) {
            if ($this->isTimeoutException($e)) {
                throw new TimeoutException('Upload timed out: ' . $e->getMessage(), null, $e);
            }
            throw new NetworkException('Upload failed: ' . $e->getMessage(), null, $e);
        } finally {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }

    /**
     * Build a MultipartStream element from a form field name and CURLFile/string.
     *
     * CURLFile values are opened as streams so the file is read lazily, never
     * buffered in its entirety in PHP userland memory. The caller must track
     * returned handles and fclose() them after the request completes.
     *
     * @param array<int, resource> $handles
     * @return array{name: string, contents: mixed, filename?: string, headers?: array<string, string>}
     * @throws ValidationException
     */
    private function buildMultipartElement(string $name, mixed $contents, array &$handles): array
    {
        if ($contents instanceof \CURLFile) {
            $filePath = $contents->getFilename();
            if (!is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
                throw new ValidationException("Upload file must be a readable, non-empty regular file: {$filePath}", 'file');
            }
            $fileHandle = fopen($filePath, 'rb');
            if ($fileHandle === false) {
                throw new ValidationException("Cannot open file for upload: {$filePath}", 'file');
            }
            $handles[] = $fileHandle;

            $filename = str_replace(["\r", "\n", '"', '\\'], '', $contents->getPostFilename() ?: basename($filePath));
            $filename = trim($filename) ?: 'upload';

            return [
                'name' => $name,
                'contents' => $fileHandle,
                'filename' => $filename,
                'headers' => [
                    'Content-Type' => mime_content_type($filePath) ?: 'application/octet-stream',
                ],
            ];
        }

        return [
            'name' => $name,
            'contents' => (string) $contents,
        ];
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
            if ($this->isTimeoutException($e)) {
                throw new TimeoutException('Download timed out: ' . $e->getMessage(), null, $e);
            }
            throw new NetworkException('Download failed: ' . $e->getMessage(), null, $e);
        }
    }
}
