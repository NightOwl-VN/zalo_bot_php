<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Modules\MessageModule;
use ZaloBot\Sdk\ZaloClient;

final class MessageModuleTest extends TestCase
{
    private function client(): MockObject&ZaloClient
    {
        return $this->getMockBuilder(ZaloClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testSendTextRejectsEmptyChatId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('chatId is required');
        (new MessageModule($this->client()))->sendText('', 'Hello');
    }

    public function testSendTextRejectsEmptyText(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('text is required');
        (new MessageModule($this->client()))->sendText('chat-1', '   ');
    }

    public function testSendTextRejectsTextLongerThan2000Characters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('2000 characters');
        (new MessageModule($this->client()))->sendText('chat-1', str_repeat('a', 2001));
    }

    public function testSendTextBuildsExpectedPayload(): void
    {
        $client = $this->client();
        $client->expects($this->once())
            ->method('post')
            ->with('sendMessage', ['chat_id' => 'chat-1', 'text' => 'Hello', 'caption' => 'Greeting'])
            ->willReturn(['ok' => true, 'result' => []]);

        $result = (new MessageModule($client))->sendText('chat-1', 'Hello', ['caption' => 'Greeting']);

        $this->assertTrue($result['ok']);
    }

    public function testSendPhotoRejectsInvalidUrl(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL');
        (new MessageModule($this->client()))->sendPhoto('chat-1', 'not-a-url');
    }

    public function testSendPhotoBuildsExpectedPayload(): void
    {
        $client = $this->client();
        $client->expects($this->once())
            ->method('post')
            ->with('sendPhoto', [
                'chat_id' => 'chat-1',
                'photo' => 'https://example.com/photo.jpg',
                'caption' => 'Photo',
            ])
            ->willReturn(['ok' => true, 'result' => []]);

        (new MessageModule($client))->sendPhoto('chat-1', 'https://example.com/photo.jpg', ['caption' => 'Photo']);
    }

    public function testSendVoiceRejectsInvalidUrl(): void
    {
        $this->expectException(ValidationException::class);
        (new MessageModule($this->client()))->sendVoice('chat-1', 'invalid');
    }

    public function testSendStickerRejectsEmptySticker(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sticker is required');
        (new MessageModule($this->client()))->sendSticker('chat-1', '');
    }

    public function testSetWebhookRequiresHttpsAndValidSecretLength(): void
    {
        $module = new MessageModule($this->client());

        try {
            $module->setWebhook('http://example.com/webhook', '12345678');
            $this->fail('Expected HTTPS validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('url', $exception->getField());
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('8-256');
        $module->setWebhook('https://example.com/webhook', 'short');
    }

    public function testDelegatesBotAndWebhookMethods(): void
    {
        $client = $this->client();
        $client->expects($this->exactly(3))->method('get')->willReturn(['ok' => true, 'result' => []]);
        $client->expects($this->exactly(3))->method('post')->willReturn(['ok' => true, 'result' => []]);
        $module = new MessageModule($client);

        $module->getMe();
        $module->getUpdates(10);
        $module->getWebhookInfo();
        $module->setWebhook('https://example.com/webhook', '12345678');
        $module->testWebhook();
        $module->deleteWebhook();
    }
}

