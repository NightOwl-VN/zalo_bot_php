<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk;

use ArrayAccess;
use ZaloBot\Sdk\Exceptions\WebhookException;
use ZaloBot\Sdk\Modules\WebhookModule;

/**
 * Typed value object for a parsed webhook event.
 *
 * Backward compatible: parseEvent() still returns this object, which is
 * array-accessible with the same keys the old plain array exposed
 * (event, eventName, userId, chatId, messageId, timestamp, message, raw).
 *
 * @implements ArrayAccess<string, mixed>
 */
final class WebhookEvent implements ArrayAccess
{
    public readonly string $event;

    /** Original raw API event name, e.g. message.text.received */
    public readonly string $eventName;

    public readonly ?string $userId;

    public readonly ?string $chatId;

    public readonly ?string $messageId;

    public readonly int $timestamp;

    /** @var array<string, mixed>|null */
    public readonly ?array $message;

    /** @var array<string, mixed> */
    public readonly array $raw;

    /**
     * @param array{event: string, eventName: string, userId: ?string, chatId: ?string, messageId: ?string, timestamp: int, message: ?array, raw: array} $data
     */
    public function __construct(array $data)
    {
        $this->event = $data['event'];
        $this->eventName = $data['eventName'];
        $this->userId = $data['userId'] ?? null;
        $this->chatId = $data['chatId'] ?? null;
        $this->messageId = $data['messageId'] ?? null;
        $this->timestamp = $data['timestamp'] ?? 0;
        $this->message = $data['message'] ?? null;
        $this->raw = $data['raw'] ?? [];
    }

    public function isText(): bool
    {
        return $this->event === 'user_text';
    }

    public function isImage(): bool
    {
        return $this->event === 'user_image';
    }

    public function isSticker(): bool
    {
        return $this->event === 'user_sticker';
    }

    public function isVoice(): bool
    {
        return $this->event === 'user_voice';
    }

    public function isFollow(): bool
    {
        return $this->event === 'user_follow';
    }

    public function isUnfollow(): bool
    {
        return $this->event === 'user_unfollow';
    }

    /**
     * @return array{event: string, eventName: string, userId: ?string, chatId: ?string, messageId: ?string, timestamp: int, message: ?array, raw: array}
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'eventName' => $this->eventName,
            'userId' => $this->userId,
            'chatId' => $this->chatId,
            'messageId' => $this->messageId,
            'timestamp' => $this->timestamp,
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }

    /**
     * @param array<string, mixed> $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['event', 'eventName', 'userId', 'chatId', 'messageId', 'timestamp', 'message', 'raw'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'event' => $this->event,
            'eventName' => $this->eventName,
            'userId' => $this->userId,
            'chatId' => $this->chatId,
            'messageId' => $this->messageId,
            'timestamp' => $this->timestamp,
            'message' => $this->message,
            'raw' => $this->raw,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new WebhookException("WebhookEvent is immutable; cannot set {$offset}");
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new WebhookException("WebhookEvent is immutable; cannot unset {$offset}");
    }
}
