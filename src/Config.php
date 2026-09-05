<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk;

use ZaloBot\Sdk\Exceptions\ValidationException;

/**
 * Configuration for Zalo Bot SDK.
 * Supports both constructor injection and environment variables.
 */
class Config
{
    public const DEFAULT_BASE_URL = 'https://bot-api.zaloplatforms.com';
    public const DEFAULT_TIMEOUT = 30000;
    public const DEFAULT_MAX_RETRIES = 3;

    public function __construct(
        protected string $botToken,
        public readonly ?string $secretKey = null,
        public readonly int $timeout = self::DEFAULT_TIMEOUT,
        public readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        public readonly string $baseURL = self::DEFAULT_BASE_URL,
    ) {
        $this->validate();
    }

    /**
     * Create config from environment variables.
     */
    public static function fromEnv(array $overrides = []): self
    {
        $env = fn (string $key, mixed $default = null): mixed =>
            $overrides[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;

        return new self(
            botToken: $env('ZALO_BOT_TOKEN') ?? throw new ValidationException(
                'botToken is required. Set ZALO_BOT_TOKEN env var.'
            ),
            secretKey: $env('ZALO_BOT_SECRET'),
            timeout: (int) ($env('ZALO_BOT_TIMEOUT') ?? self::DEFAULT_TIMEOUT),
            maxRetries: (int) ($env('ZALO_BOT_MAX_RETRIES') ?? self::DEFAULT_MAX_RETRIES),
            baseURL: $env('ZALO_BOT_BASE_URL') ?? self::DEFAULT_BASE_URL,
        );
    }

    public function getBotToken(): string
    {
        return $this->botToken;
    }

    public function getMaskedToken(): string
    {
        return strlen($this->botToken) > 6
            ? substr($this->botToken, 0, 6) . '...'
            : '***';
    }

    public function hasSecretKey(): bool
    {
        return is_string($this->secretKey) && strlen($this->secretKey) >= 8;
    }

    public function toArray(bool $includeSecrets = false, bool $fullToken = false): array
    {
        $result = [
            'botToken' => $fullToken ? $this->botToken : $this->getMaskedToken(),
            'timeout' => $this->timeout,
            'maxRetries' => $this->maxRetries,
            'baseURL' => $this->baseURL,
        ];
        if ($includeSecrets) {
            $result['secretKey'] = $this->secretKey;
        }
        return $result;
    }

    private function validate(): void
    {
        if (!is_string($this->botToken) || trim($this->botToken) === '') {
            throw new ValidationException('botToken must be a non-empty string', 'botToken');
        }
        if ($this->timeout < 0) {
            throw new ValidationException('timeout must be a non-negative integer', 'timeout');
        }
        if ($this->maxRetries < 0) {
            throw new ValidationException('maxRetries must be a non-negative integer', 'maxRetries');
        }
        if (!filter_var($this->baseURL, FILTER_VALIDATE_URL)) {
            throw new ValidationException("baseURL is not valid: {$this->baseURL}", 'baseURL');
        }
    }

    public function __toString(): string
    {
        return sprintf(
            'ZaloBotConfig(token=%s, timeout=%d, maxRetries=%d, baseURL=%s)',
            $this->getMaskedToken(),
            $this->timeout,
            $this->maxRetries,
            $this->baseURL,
        );
    }
}
