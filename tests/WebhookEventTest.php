<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Modules\WebhookModule;
use ZaloBot\Sdk\WebhookEvent;

final class WebhookEventTest extends TestCase
{
    private const SECRET = 'webhook-secret-123';

    public function testParseEventReturnsArrayWithAllKeys(): void
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

        // Backward compatible: returns plain array with legacy keys
        $event = $module->parseEvent($payload);

        $this->assertIsArray($event);
        $this->assertArrayHasKey('event', $event);
        $this->assertArrayHasKey('eventName', $event);
        $this->assertArrayHasKey('userId', $event);
        $this->assertArrayHasKey('chatId', $event);
        $this->assertArrayHasKey('messageId', $event);
        $this->assertArrayHasKey('timestamp', $event);
        $this->assertArrayHasKey('message', $event);
        $this->assertArrayHasKey('raw', $event);
    }

    public function testParseEventDtoReturnsDto(): void
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

        $event = $module->parseEventDto($payload);

        $this->assertInstanceOf(WebhookEvent::class, $event);
    }

    public function testParseEventDtoIsArrayAccessible(): void
    {
        $module = new WebhookModule(self::SECRET);
        $event = $module->parseEventDto([
            'event_name' => 'user.follow',
            'message' => ['id' => 'user-3'],
        ]);

        $this->assertSame('user_follow', $event['event']);
        $this->assertSame('user-3', $event['userId']);
        $this->assertSame('user_follow', $event->event);
        $this->assertSame('user-3', $event->userId);
    }

    public function testParseEventDtoHelpers(): void
    {
        $module = new WebhookModule(self::SECRET);
        $textEvent = $module->parseEventDto([
            'event_name' => 'message.text.received',
            'message' => ['from' => ['id' => 'u1'], 'text' => 'hi'],
        ]);
        $followEvent = $module->parseEventDto([
            'event_name' => 'user.follow',
            'message' => ['id' => 'u2'],
        ]);

        $this->assertTrue($textEvent->isText());
        $this->assertFalse($textEvent->isFollow());
        $this->assertTrue($followEvent->isFollow());
        $this->assertFalse($followEvent->isText());
    }

    public function testParseFlatPayload(): void
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

        $this->assertIsArray($event);
        $this->assertArrayHasKey('event', $event);
        $this->assertArrayHasKey('message', $event);
    }
}
