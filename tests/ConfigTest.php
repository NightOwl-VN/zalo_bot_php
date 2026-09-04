<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Config;
use ZaloBot\Sdk\Exceptions\ValidationException;

final class ConfigTest extends TestCase
{
    private const TOKEN = '123456789:abc-def-ghi-jkl';

    public function testCanBeCreatedWithValidValues(): void
    {
        $config = new Config(
            botToken: self::TOKEN,
            secretKey: 'super-secret-key',
            timeout: 15000,
            maxRetries: 5,
            baseURL: 'https://bot-api.zaloplatforms.com',
        );

        $this->assertSame(self::TOKEN, $config->getBotToken());
        $this->assertSame(15000, $config->timeout);
        $this->assertSame(5, $config->maxRetries);
    }

    public function testAppliesDefaultConstants(): void
    {
        $config = new Config(botToken: self::TOKEN);

        $this->assertSame(Config::DEFAULT_BASE_URL, $config->baseURL);
        $this->assertSame(Config::DEFAULT_TIMEOUT, $config->timeout);
        $this->assertSame(Config::DEFAULT_MAX_RETRIES, $config->maxRetries);
        $this->assertSame('https://bot-api.zaloplatforms.com', Config::DEFAULT_BASE_URL);
    }

    public function testRejectsEmptyBotToken(): void
    {
        $this->expectException(ValidationException::class);

        new Config(botToken: '   ');
    }

    public function testRejectsNegativeTimeout(): void
    {
        $this->expectException(ValidationException::class);

        new Config(botToken: self::TOKEN, timeout: -1);
    }

    public function testRejectsNegativeMaxRetries(): void
    {
        $this->expectException(ValidationException::class);

        new Config(botToken: self::TOKEN, maxRetries: -1);
    }

    public function testRejectsInvalidBaseUrl(): void
    {
        $this->expectException(ValidationException::class);

        new Config(botToken: self::TOKEN, baseURL: 'not-a-url');
    }

    public function testMasksLongTokens(): void
    {
        $config = new Config(botToken: self::TOKEN);

        $this->assertSame('123456...', $config->getMaskedToken());
        $this->assertStringNotContainsString('abc-def', $config->getMaskedToken());
    }

    public function testMasksShortTokensAsAsterisks(): void
    {
        $config = new Config(botToken: 'short');

        $this->assertSame('***', $config->getMaskedToken());
    }

    public function testHasSecretKeyRequiresAtLeastEightChars(): void
    {
        $withKey = new Config(botToken: self::TOKEN, secretKey: '12345678');
        $shortKey = new Config(botToken: self::TOKEN, secretKey: 'short');
        $noKey = new Config(botToken: self::TOKEN);

        $this->assertTrue($withKey->hasSecretKey());
        $this->assertFalse($shortKey->hasSecretKey());
        $this->assertFalse($noKey->hasSecretKey());
    }

    public function testToArrayMasksTokenByDefault(): void
    {
        $config = new Config(botToken: self::TOKEN, secretKey: '12345678');

        $array = $config->toArray();

        $this->assertSame('123456...', $array['botToken']);
        $this->assertArrayNotHasKey('secretKey', $array);
    }

    public function testToArrayCanIncludeSecretsAndFullToken(): void
    {
        $config = new Config(botToken: self::TOKEN, secretKey: '12345678');

        $array = $config->toArray(includeSecrets: true, fullToken: true);

        $this->assertSame(self::TOKEN, $array['botToken']);
        $this->assertSame('12345678', $array['secretKey']);
    }

    public function testFromEnvReadsEnvironmentVariables(): void
    {
        $config = Config::fromEnv([
            'ZALO_BOT_TOKEN' => self::TOKEN,
            'ZALO_BOT_SECRET' => 'env-secret',
            'ZALO_BOT_TIMEOUT' => '5000',
            'ZALO_BOT_MAX_RETRIES' => '7',
        ]);

        $this->assertSame(self::TOKEN, $config->getBotToken());
        $this->assertSame('env-secret', $config->secretKey);
        $this->assertSame(5000, $config->timeout);
        $this->assertSame(7, $config->maxRetries);
    }

    public function testFromEnvFallsBackToDefaults(): void
    {
        $config = Config::fromEnv(['ZALO_BOT_TOKEN' => self::TOKEN]);

        $this->assertSame(Config::DEFAULT_TIMEOUT, $config->timeout);
        $this->assertSame(Config::DEFAULT_MAX_RETRIES, $config->maxRetries);
        $this->assertNull($config->secretKey);
    }

    public function testFromEnvThrowsWhenTokenMissing(): void
    {
        // Ensure no real environment token leaks into the test.
        putenv('ZALO_BOT_TOKEN');
        unset($_ENV['ZALO_BOT_TOKEN']);

        $this->expectException(ValidationException::class);

        Config::fromEnv();
    }

    public function testToStringMasksToken(): void
    {
        $config = new Config(botToken: self::TOKEN);

        $this->assertStringContainsString('123456...', (string) $config);
        $this->assertStringNotContainsString('abc-def', (string) $config);
    }
}
