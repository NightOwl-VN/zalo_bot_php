<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\WebhookException;
use ZaloBot\Sdk\Modules\WebhookModule;

final class WebhookModuleTest extends TestCase
{
    private const SECRET = 'webhook-secret-123';

    public function testEventMapContainsAllSupportedEvents(): void
    {
        $this->assertSame('user_text', WebhookModule::EVENT_MAP['message.text.received']);
        $this->assertSame('user_image', WebhookModule::EVENT_MAP['message.image.received']);
        $this->assertSame('user_sticker', WebhookModule::EVENT_MAP['message.sticker.received']);
        $this->assertSame('user_voice', WebhookModule::EVENT_MAP['message.voice.received']);
        $this->assertSame('user_unsupported', WebhookModule::EVENT_MAP['message.unsupported.received']);
        $this->assertSame('user_follow', WebhookModule::EVENT_MAP['user.follow']);
        $this->assertSame('user_unfollow', WebhookModule::EVENT_MAP['user.unfollow']);
    }

    public function testVerifyAcceptsCorrectHeaderCaseInsensitively(): void
    {
        $module = new WebhookModule(self::SECRET);

        $this->assertTrue($module->verify(['x-bot-api-secret-token' => self::SECRET]));
        $this->assertTrue($module->verify(['X-BOT-API-SECRET-TOKEN' => self::SECRET]));
    }

    public function testVerifyRejectsMissingAndIncorrectToken(): void
    {
        $module = new WebhookModule(self::SECRET);

        $this->assertFalse($module->verify([]));
        $this->assertFalse($module->verify(['X-Bot-Api-Secret-Token' => 'wrong-token']));
    }

    public function testRequireValidThrowsForInvalidToken(): void
    {
        $module = new WebhookModule(self::SECRET);

        $this->expectException(WebhookException::class);
        $this->expectExceptionCode(403);

        $module->requireValid([]);
    }

    public function testVerifyCanBeConfiguredWithoutASecret(): void
    {
        $optional = new WebhookModule(null, false);
        $required = new WebhookModule(null, true);

        $this->assertTrue($optional->verify([]));
        $this->assertFalse($required->verify([]));
    }

    public function testParseWrappedTextEvent(): void
    {
        $module = new WebhookModule(self::SECRET);
        $payload = [
            'ok' => true,
            'result' => [
                'event_name' => 'message.text.received',
                'message' => [
                    'message_id' => 'msg-1',
                    'date' => 1623456789,
                    'from' => ['id' => 'user-1'],
                    'chat' => ['id' => 'chat-1'],
                    'text' => 'Hello',
                ],
            ],
        ];

        $event = $module->parseEvent($payload);

        $this->assertSame('user_text', $event['event']);
        $this->assertSame('message.text.received', $event['eventName']);
        $this->assertSame('user-1', $event['userId']);
        $this->assertSame('chat-1', $event['chatId']);
        $this->assertSame('msg-1', $event['messageId']);
        $this->assertSame(1623456789, $event['timestamp']);
        $this->assertSame(['text' => 'Hello'], $event['message']);
        $this->assertSame($payload, $event['raw']);
    }

    public function testParseFlatImageEventAndUserChatFallback(): void
    {
        $module = new WebhookModule(self::SECRET);
        $event = $module->parseEvent([
            'event_name' => 'message.image.received',
            'message' => [
                'from' => ['id' => 'user-2'],
                'photo' => ['url' => 'https://example.com/photo.jpg'],
                'caption' => 'A photo',
            ],
        ]);

        $this->assertSame('user_image', $event['event']);
        $this->assertSame('user-2', $event['chatId']);
        $this->assertSame([
            'photo' => ['url' => 'https://example.com/photo.jpg'],
            'caption' => 'A photo',
        ], $event['message']);
    }

    public function testParseEventThrowsWhenEventNameIsMissing(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionCode(400);

        (new WebhookModule(self::SECRET))->parseEvent(['message' => []]);
    }

    public function testHandleVerifiesParsesAndInvokesHandler(): void
    {
        $module = new WebhookModule(self::SECRET);
        $event = $module->handle(
            static fn (array $parsed): array => $parsed,
            ['X-Bot-Api-Secret-Token' => self::SECRET],
            [
                'event_name' => 'user.follow',
                'message' => ['id' => 'user-3'],
            ],
        );

        $this->assertSame('user_follow', $event['event']);
        $this->assertSame('user-3', $event['userId']);
    }
}

