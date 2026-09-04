<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\ZaloClient;
use ZaloBot\Sdk\Config;
use ZaloBot\Sdk\Modules\MediaModule;
use ZaloBot\Sdk\Modules\MessageModule;
use ZaloBot\Sdk\Modules\UserModule;
use ZaloBot\Sdk\Modules\WebhookModule;

final class ZaloBotTest extends TestCase
{
    private const TOKEN = '123456789:abc-def-ghi-jkl';

    public function testCanBeInstantiatedWithConfigArray(): void
    {
        $bot = new ZaloBot(['botToken' => self::TOKEN]);

        $this->assertSame(self::TOKEN, $bot->client->getBotToken());
        $this->assertInstanceOf(Config::class, $bot->config);
        $this->assertInstanceOf(ZaloClient::class, $bot->client);
        $this->assertInstanceOf(MessageModule::class, $bot->message);
        $this->assertInstanceOf(UserModule::class, $bot->user);
        $this->assertInstanceOf(WebhookModule::class, $bot->webhook);
        $this->assertInstanceOf(MediaModule::class, $bot->media);
    }

    public function testCanBeInstantiatedWithConfigObject(): void
    {
        $config = Config::fromEnv(['ZALO_BOT_TOKEN' => self::TOKEN]);
        $bot = new ZaloBot($config);

        $this->assertSame($config, $bot->config);
        $this->assertSame(self::TOKEN, $bot->client->getBotToken());
    }

    public function testCanBeCreatedFromEnv(): void
    {
        $bot = ZaloBot::fromEnv([
            'ZALO_BOT_TOKEN' => self::TOKEN,
            'ZALO_BOT_SECRET' => 'test-secret-1234',
            'ZALO_BOT_TIMEOUT' => '12000',
            'ZALO_BOT_MAX_RETRIES' => '4',
        ]);

        $this->assertSame(self::TOKEN, $bot->config->getBotToken());
        $this->assertSame('test-secret-1234', $bot->config->secretKey);
        $this->assertSame(12000, $bot->config->timeout);
        $this->assertSame(4, $bot->config->maxRetries);
    }

    public function testGetConfigMasksTokenByDefault(): void
    {
        $bot = new ZaloBot(['botToken' => self::TOKEN]);
        $safe = $bot->getConfig();

        $this->assertSame('123456...', $safe['botToken']);
        $this->assertArrayNotHasKey('secretKey', $safe);
    }

    public function testGetConfigCanIncludeSecretsAndFullToken(): void
    {
        $bot = ZaloBot::fromEnv([
            'ZALO_BOT_TOKEN' => self::TOKEN,
            'ZALO_BOT_SECRET' => 'secret-1234',
        ]);
        $full = $bot->getConfig(includeSecrets: true, fullToken: true);

        $this->assertSame(self::TOKEN, $full['botToken']);
        $this->assertSame('secret-1234', $full['secretKey']);
    }

    public function testSetBotTokenUpdatesClientAtRuntime(): void
    {
        $bot = new ZaloBot(['botToken' => self::TOKEN]);
        $newToken = '999999999:new-token-abc';

        $bot->setBotToken($newToken);

        $this->assertSame($newToken, $bot->client->getBotToken());
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(\ZaloBot\Sdk\Exceptions\ValidationException::class);

        new ZaloBot(['botToken' => '']);
    }
}

