<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Modules;

use ZaloBot\Sdk\Exceptions\WebhookException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\WebhookEvent;

/**
 * Webhook module - Parse and verify Zalo Bot webhook events.
 *
 * Authentication via X-Bot-Api-Secret-Token header (timing-safe comparison).
 * Reference: https://bot.zapps.me/docs/webhook/
 */
class WebhookModule
{
    /**
     * Map raw Zalo event names to normalized canonical names.
     */
    public const EVENT_MAP = [
        'message.text.received' => 'user_text',
        'message.image.received' => 'user_image',
        'message.sticker.received' => 'user_sticker',
        'message.voice.received' => 'user_voice',
        'message.unsupported.received' => 'user_unsupported',
        'user.follow' => 'user_follow',
        'user.unfollow' => 'user_unfollow',
    ];

    public function __construct(
        protected ?string $secretKey = null,
        protected bool $requireSecret = true,
    ) {
    }

    /**
     * Verify webhook request using timing-safe comparison.
     *
     * @param array<string,string>|object $headers Key-value array of headers or request object
     */
    public function verify(array|object $headers): bool
    {
        if ($this->secretKey === null) {
            return !$this->requireSecret;
        }

        $token = $this->extractSecretToken($headers);
        if ($token === null) {
            return !$this->requireSecret;
        }

        return hash_equals($this->secretKey, $token);
    }

    /**
     * Verify and throw WebhookException if invalid.
     */
    public function requireValid(array|object $headers): void
    {
        if (!$this->verify($headers)) {
            throw new WebhookException('Invalid webhook secret token', 403);
        }
    }

    /**
     * Parse webhook event payload.
     *
     * Supports both wrapped {ok, result: {event_name, message}} and flat {event_name, message}.
     * Returns a WebhookEvent DTO which is also array-accessible with the
     * legacy keys (event, eventName, userId, chatId, messageId, timestamp, message, raw)
     * for backward compatibility.
     *
     * @param array<string, mixed> $payload Decoded JSON payload
     * @return array{event: string, eventName: string, userId: ?string, chatId: ?string, messageId: ?string, timestamp: int, message: ?array, raw: array}
     */
    public function parseEvent(array $payload): array
    {
        $result = isset($payload['result']) && is_array($payload['result'])
            ? $payload['result']
            : $payload;

        $eventName = $result['event_name'] ?? null;
        if ($eventName === null) {
            throw new WebhookException('Missing event_name field in payload', 400);
        }

        $msg = $result['message'] ?? null;

        $userId = null;
        $chatId = null;

        if (isset($msg['from']['id'])) {
            $userId = (string) $msg['from']['id'];
            $chatId = isset($msg['chat']['id']) ? (string) $msg['chat']['id'] : $userId;
        } elseif (isset($msg['id'])) {
            $userId = (string) $msg['id'];
            $chatId = $userId;
        }

        $normalizedEvent = self::EVENT_MAP[$eventName] ?? $eventName;

        $message = null;
        if ($msg !== null) {
            $message = match ($normalizedEvent) {
                'user_text' => ['text' => $msg['text'] ?? null],
                'user_image' => [
                    'photo' => $msg['photo'] ?? null,
                    'caption' => $msg['caption'] ?? null,
                ],
                'user_sticker' => ['sticker' => $msg['sticker'] ?? null],
                'user_voice' => ['voiceUrl' => $msg['voice_url'] ?? null],
                default => $msg,
            };
        }

        return [
            'event' => $normalizedEvent,
            'eventName' => $eventName,
            'userId' => $userId,
            'chatId' => $chatId,
            'messageId' => $msg['message_id'] ?? null,
            'timestamp' => $msg['date'] ?? (int) (microtime(true) * 1000),
            'message' => $message,
            'raw' => $payload,
        ];
    }

    /**
     * Parse event and return a typed WebhookEvent DTO.
     * For backward compatibility, parseEvent() returns a plain array.
     * Use this method when you want typed access with convenience helpers.
     */
    public function parseEventDto(array $payload): WebhookEvent
    {
        return new WebhookEvent($this->parseEvent($payload));
    }

    /**
     * Process a webhook request: verify, parse, and invoke handler callback.
     *
     * @param callable(array $event): mixed $handler Called with parsed event data
     * @param array|null $headers HTTP headers (null = getallheaders())
     * @param array|null $body Decoded JSON body (null = php://input)
     */
    public function handle(callable $handler, ?array $headers = null, ?array $body = null): mixed
    {
        $headers ??= getallheaders();
        $this->requireValid($headers);

        $body ??= json_decode(file_get_contents('php://input'), true) ?? [];
        $event = $this->parseEvent($body);

        return $handler($event);
    }

    private function extractSecretToken(array|object $headers): ?string
    {
        if (is_array($headers)) {
            // Case-insensitive header lookup
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-bot-api-secret-token') {
                    return is_array($value) ? $value[0] : (string) $value;
                }
            }
        } elseif (is_object($headers) && method_exists($headers, 'getHeaderLine')) {
            $token = $headers->getHeaderLine('X-Bot-Api-Secret-Token');
            return $token !== '' ? $token : null;
        }

        return null;
    }
}
