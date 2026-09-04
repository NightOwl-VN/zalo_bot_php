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

class ZaloClient
{
    public const API_BASE_URL = 'https://bot-api.zaloplatforms.com';

    /**
     * @param array{
     *   botToken: string,
     *   int $timeout,
     *   int $maxRetries,
     *   string $baseURL
     * } $config
     */
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

    public function getRequestBaseUrl(): string
    {
        return "{$this->baseURL}/bot{$this->botToken}";
    }

    /**
     * @throws ApiException|RateLimitException|AuthException|TimeoutException|NetworkException
     */
    private function request(string $method, string $apiMethod, array $data = [], array $options = []): mixed
    {
        $url = "{$this->getRequestBaseUrl()}/{$apiMethod}";
        $client = new Client([
            'base_uri' => $this->getRequestBaseUrl(),
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $client->request($method, $apiMethod, [
                    'json' => array_merge($data, $options),
                    'timeout' => $this->timeout,
                ]);

                $body = json_decode($response->getBody(), true);

                // API returns 200 with ok:false => throw ApiError
                if ($body !== null && $body['ok'] === false) {
                    $errorCode = $body['error_code'] ?? -1;
                    $description = $body['description'] ?? 'Unknown error';
                    throw new ApiException(
                        $description,
                        $errorCode,
                        $response->getStatusCode(),
                        $body,
                    );
                }

                return $body ?? $response->getBody();

            } catch (ClientException $e) {
                // Only retry on 429 rate limit
                if ($attempt < $this->maxRetries && $e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                    // Calculate delay with exponential backoff + jitter
                    $delay = min(1000 * pow(2, $attempt) + rand(0, 1000), 30000);
                    sleep($delay / 1000);
                    continue;
                }

                // Translate error
                $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                $body = json_decode($e->getResponse()->getBody(), true) ?? [];

                if ($status === 401 || $status === 403) {
                    throw new AuthException(
                        $body['description'] ?? 'Invalid or expired bot token',
                        $status,
                        $body,
                    );
                }

                throw new NetworkException(
                    $e->getMessage(),
                    $body,
                );
            } catch (\Throwable $e) {
                if ($attempt < $this->maxRetries) {
                    $delay = min(1000 * pow(2, $attempt) + rand(0, 1000), 30000);
                    sleep($delay / 1000);
                    continue;
                }
                throw new TimeoutException(
                    'Request timed out after ' . $this->timeout . 'ms',
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
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->getRequestBaseUrl(),
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
        ]);

        $response = $client->post($method, [
            'form_params' => $formData,
            'timeout' => $this->timeout,
        ]);

        $body = json_decode($response->getBody(), true);
        if ($body !== null && $body['ok'] === false) {
            $errorCode = $body['error_code'] ?? -1;
            $description = $body['description'] ?? 'Unknown error';
            throw new ApiException($description, $errorCode, $response->getStatusCode(), $body);
        }
        return $body;
    }
}