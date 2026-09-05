<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Modules;

use ZaloBot\Sdk\ZaloClient;
use ZaloBot\Sdk\Exceptions\ValidationException;

/**
 * Message module - Send and manage Zalo Bot messages.
 * API Reference: https://bot.zapps.me/docs/apis/sendMessage/
 */
class MessageModule
{
    public function __construct(protected ZaloClient $client)
    {
    }

    /**
     * Send a text message to a user or chat.
     *
     * @param array{caption?:string} $options
     * @return array{ok:bool,result:array}
     */
    public function sendText(string $chatId, string $text, array $options = []): array
    {
        $this->validateChatId($chatId);
        $this->validateMessageText($text);

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if (!empty($options['caption'])) {
            $payload['caption'] = $options['caption'];
        }

        return $this->client->post('sendMessage', $payload);
    }

    /**
     * Send a photo message.
     *
     * @param array{caption?:string} $options
     * @return array{ok:bool,result:array}
     */
    public function sendPhoto(string $chatId, string $photoUrl, array $options = []): array
    {
        $this->validateChatId($chatId);
        $this->validateUrl($photoUrl, 'photo');

        $payload = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
        ];
        if (!empty($options['caption'])) {
            $payload['caption'] = $options['caption'];
        }

        return $this->client->post('sendPhoto', $payload);
    }

    /**
     * Send a sticker message.
     *
     * @return array{ok:bool,result:array}
     */
    public function sendSticker(string $chatId, string $stickerId): array
    {
        $this->validateChatId($chatId);
        if (trim($stickerId) === '') {
            throw new ValidationException('sticker is required', 'sticker');
        }

        return $this->client->post('sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $stickerId,
        ]);
    }

    /**
     * Send a voice message (1-1 chats only, .aac audio URL).
     *
     * @return array{ok:bool,result:array}
     */
    public function sendVoice(string $chatId, string $voiceUrl): array
    {
        $this->validateChatId($chatId);
        $this->validateUrl($voiceUrl, 'voice_url');

        return $this->client->post('sendVoice', [
            'chat_id' => $chatId,
            'voice_url' => $voiceUrl,
        ]);
    }

    /**
     * Send a chat action ('typing' or 'upload_photo').
     *
     * @return array{ok:bool}
     */
    public function sendChatAction(string $chatId, string $action): array
    {
        $this->validateChatId($chatId);
        if (trim($action) === '') {
            throw new ValidationException('action is required', 'action');
        }

        return $this->client->post('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * Get bot info.
     *
     * @return array{ok:bool,result:array{id:string,account_name:string,account_type:string}}
     */
    public function getMe(): array
    {
        return $this->client->get('getMe');
    }

    /**
     * Get updates via long polling (only when no webhook configured).
     *
     * @return array Array of updates
     */
    public function getUpdates(int $timeout = 30): array
    {
        return $this->client->get('getUpdates', ['timeout' => $timeout]);
    }

    /**
     * Set webhook URL.
     *
     * Sends both secretToken and secret_token because the live API
     * expects snake_case while older docs show camelCase.
     *
     * @return array{ok:bool,result:array{url:string,updated_at:int,verification:array}}
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $this->validateHttpsUrl($url, 'url');

        $len = strlen($secretToken);
        if ($len < 8 || $len > 256) {
            throw new ValidationException('secretToken must be 8-256 chars', 'secretToken');
        }

        return $this->client->post('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
        ]);
    }

    /**
     * Test webhook URL.
     *
     * @return array{ok:bool,result:array{ok:bool,url:string,status_code:int,outcome:string,latency_ms:int,hint:string}}
     */
    public function testWebhook(): array
    {
        return $this->client->post('testWebhook');
    }

    /**
     * Delete webhook configuration.
     *
     * @return array{ok:bool,result:array{url:string,updated_at:int}}
     */
    public function deleteWebhook(): array
    {
        return $this->client->post('deleteWebhook');
    }

    /**
     * Get current webhook info.
     *
     * @return array{ok:bool,result:array{url:string,updated_at:int}}
     */
    public function getWebhookInfo(): array
    {
        return $this->client->get('getWebhookInfo');
    }

    // ─────────────────────────────────────────────
    //  Validation helpers
    // ─────────────────────────────────────────────

    private function validateChatId(string $chatId): void
    {
        if (trim($chatId) === '') {
            throw new ValidationException('chatId is required', 'chatId');
        }
    }

    private function validateMessageText(string $text): void
    {
        if (trim($text) === '') {
            throw new ValidationException('text is required', 'text');
        }
        if (mb_strlen($text) > 2000) {
            throw new ValidationException('text must not exceed 2000 characters', 'text');
        }
    }

    private function validateUrl(string $url, string $field): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException("Invalid URL: {$url}", $field);
        }
    }

    private function validateHttpsUrl(string $url, string $field): void
    {
        if (!str_starts_with($url, 'https://')) {
            throw new ValidationException('URL must be HTTPS', $field);
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException("Invalid URL: {$url}", $field);
        }
    }
}
