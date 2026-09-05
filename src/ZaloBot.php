<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk;

use ZaloBot\Sdk\Modules\MessageModule;
use ZaloBot\Sdk\Modules\UserModule;
use ZaloBot\Sdk\Modules\WebhookModule;
use ZaloBot\Sdk\Modules\MediaModule;
use ZaloBot\Sdk\Exceptions\ValidationException;

/**
 * Main Zalo Bot class — entry point for all SDK features.
 */
class ZaloBot
{
    public readonly Config $config;
    public readonly ZaloClient $client;
    public readonly MessageModule $message;
    public readonly UserModule $user;
    public readonly WebhookModule $webhook;
    public readonly MediaModule $media;

    public function __construct(array|Config $config)
    {
        $this->config = $config instanceof Config ? $config : new Config(
            botToken: $config['botToken'] ?? throw new ValidationException('botToken is required', 'botToken'),
            secretKey: $config['secretKey'] ?? null,
            timeout: (int) ($config['timeout'] ?? 30000),
            maxRetries: (int) ($config['maxRetries'] ?? 3),
            baseURL: $config['baseURL'] ?? Config::DEFAULT_BASE_URL,
        );

        $this->client = new ZaloClient(
            botToken: $this->config->getBotToken(),
            timeout: $this->config->timeout,
            maxRetries: $this->config->maxRetries,
            baseURL: $this->config->baseURL,
        );

        $this->message = new MessageModule($this->client);
        $this->user = new UserModule($this->client);
        $this->webhook = new WebhookModule(
            secretKey: $this->config->secretKey,
        );
        $this->media = new MediaModule($this->client);
    }

    /**
     * Create bot from environment variables.
     * The consuming app must load .env before calling this (e.g. vlucas/phpdotenv).
     */
    public static function fromEnv(array $overrides = []): self
    {
        return new self(Config::fromEnv($overrides));
    }

    /**
     * Update bot token at runtime.
     */
    public function setBotToken(string $newToken): void
    {
        if (trim($newToken) === '') {
            throw new ValidationException('Invalid bot token', 'botToken');
        }
        $this->client->updateBotToken($newToken);
    }

    /**
     * Get config as safe plain object (excludes secretKey by default).
     */
    public function getConfig(bool $includeSecrets = false, bool $fullToken = false): array
    {
        return $this->config->toArray($includeSecrets, $fullToken);
    }
}
