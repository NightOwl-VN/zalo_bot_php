<?php

declare(strict_types=1);

namespace ZaloBot\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\NetworkException;
use ZaloBot\Sdk\Exceptions\TimeoutException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Exceptions\ZaloBotException;
use RuntimeException;

class ZaloClient
{
    public const API_BASE_URL = 'https://bot-api.zaloplatforms.com';

    public function __construct(
        protected string $botToken,
        protected int $timeout = 30000,
        protected int $maxRetries = 3,
        protected string $baseURL = self::API_BASE_URL,
    ) {
    }

    public function getBotToken(): string
    {
        return $this->botToken;
    }

    /**
     * Update bot token at runtime.
     */
    public function updateBotToken(string $newToken): void
    {
        if (trim($newToken) === '') {
            throw new ValidationException('botToken must be a non-empty string', 'botToken');
        }
        $this->botToken = trim($newToken);
    }

    public function getRequestBaseUrl(): string
    {
        return "{$this->baseURL}/bot{$this->botToken}";
    }

    /**
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    private function request(string $method, string $apiMethod, array $data = [], array $options = []): mixed
    {
        $client = new Client([
            'base_uri' => $this->getRequestBaseUrl() . '/',
            'timeout' => $this->timeout / 1000,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $payloadKey = strtoupper($method) === 'GET' ? 'query' : 'json';
                $response = $client->request($method, $apiMethod, [
                    $payloadKey => array_merge($data, $options),
                    'timeout' => $this->timeout / 1000,
                ]);

                $body = json_decode((string) $response->getBody(), true);

                // API returns 200 with ok:false => throw ApiError
                if ($body !== null && isset($body['ok']) && $body['ok'] === false) {
                    $errorCode = $body['error_code'] ?? -1;
                    $description = $body['description'] ?? 'Unknown error';
                    throw new ApiException(
                        $description,
                        $errorCode,
                        $response->getStatusCode(),
                        $body,
                    );
                }

                return $body ?? (string) $response->getBody();

            } catch (ZaloBotException $e) {
                // Do not convert our own custom domain exceptions into network timeouts
                throw $e;
            } catch (ClientException $e) {
                // Only retry on 429 rate limit
                if ($attempt < $this->maxRetries && $e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                    $delay = min(1000 * pow(2, $attempt) + rand(0, 1000), 30000);
                    usleep((int) ($delay * 1000));
                    continue;
                }

                // Translate error
                $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                $body = $e->getResponse() ? json_decode((string) $e->getResponse()->getBody(), true) ?? [] : [];

                if ($status === 401 || $status === 403) {
                    throw new AuthException(
                        $body['description'] ?? 'Invalid or expired bot token',
                        $status,
                        $body,
                    );
                }

                if ($status === 429) {
                    throw new RateLimitException(
                        $body['description'] ?? 'Rate limit exceeded',
                        $status,
                        null,
                        $body,
                    );
                }

                throw new ApiException(
                    $body['description'] ?? $e->getMessage(),
                    $body['error_code'] ?? -1,
                    $status,
                    $body,
                );
            } catch (\Throwable $e) {
                if ($attempt < $this->maxRetries) {
                    $delay = min(1000 * pow(2, $attempt) + rand(0, 1000), 30000);
                    usleep((int) ($delay * 1000));
                    continue;
                }
                throw new TimeoutException(
                    'Request timed out or failed: ' . $e->getMessage(),
                    null,
                    $e
                );
            }
        }

        throw new RuntimeException('Request failed after max retries');
    }

    /** @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException */
    public function get(string $method, array $params = []): mixed
    {
        return $this->request('GET', $method, $params);
    }

    /** @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException */
    public function post(string $method, array $data = []): mixed
    {
        return $this->request('POST', $method, $data);
    }

    /** @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException */
    public function upload(string $method, array $formData): mixed
    {
        $client = new Client([
            'base_uri' => $this->getRequestBaseUrl() . '/',
            'timeout' => $this->timeout / 1000,
        ]);

        $multipart = [];
        foreach ($formData as $name => $contents) {
            $multipart[] = [
                'name' => $name,
                'contents' => $contents instanceof \CURLFile ? fopen($contents->getFilename(), 'r') : $contents,
                'filename' => $contents instanceof \CURLFile ? $contents->getPostFilename() : null,
            ];
        }

        $response = $client->post($method, [
            'multipart' => $multipart,
            'timeout' => $this->timeout / 1000,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if ($body !== null && isset($body['ok']) && $body['ok'] === false) {
            $errorCode = $body['error_code'] ?? -1;
            $description = $body['description'] ?? 'Unknown error';
            throw new ApiException($description, $errorCode, $response->getStatusCode(), $body);
        }
        return $body;
    }
}
