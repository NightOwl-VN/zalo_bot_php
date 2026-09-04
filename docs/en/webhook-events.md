# Webhook Events

This document covers webhook setup, token verification, and handling incoming events with the PHP SDK.

---

## Setting Up a Webhook

1. Go to [Zalo Bot Platform](https://bot.zapps.me/)
2. Select your bot
3. Navigate to **Webhook**
4. Enter your webhook URL (must be HTTPS)
5. Configure the same secret token in your SDK's `ZALO_BOT_SECRET` environment variable

> ⚠️ Your webhook must respond with `200 OK` within **5 seconds**. Zalo may retry requests that are not acknowledged promptly.

---

## Token Verification

Zalo sends the secret token in `X-Bot-Api-Secret-Token`. The SDK compares it with PHP's timing-safe `hash_equals()`.

### Standalone PHP endpoint

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

$bot = ZaloBot::fromEnv();
$headers = getallheaders();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!$bot->webhook->verify($headers)) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

try {
    $event = $bot->webhook->parseEvent($body);
    // Process the event. Queue long-running work when possible.
    http_response_code(200);
    echo json_encode(['message' => 'OK']);
} catch (\ZaloBot\Sdk\Exceptions\WebhookException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['message' => $e->getMessage()]);
}
```

### Convenience handler

`handle()` verifies the headers, parses the body, and calls your handler:

```php
$bot->webhook->handle(
    function (array $event) use ($bot): void {
        if ($event['event'] === 'user_text') {
            $bot->message->sendText($event['chatId'], $event['message']['text']);
        }
    },
    getallheaders(),
    json_decode(file_get_contents('php://input'), true) ?? []
);
```

`requireValid($headers)` is useful when you want verification to throw rather than return a boolean.

---

## Event Payload Format

`parseEvent()` accepts both wrapped and flat formats.

### Wrapped (standard from Zalo)

```json
{
  "ok": true,
  "result": {
    "event_name": "message.text.received",
    "message": {
      "message_id": "msg_xyz",
      "date": 1623456789,
      "from": { "id": "123456789" },
      "chat": { "id": "123456789" },
      "text": "Hello bot!"
    }
  }
}
```

### Normalized event object

```php
[
    'event' => 'user_text',
    'eventName' => 'message.text.received',
    'userId' => '123456789',
    'chatId' => '123456789',
    'messageId' => 'msg_xyz',
    'timestamp' => 1623456789,
    'message' => ['text' => 'Hello bot!'],
    'raw' => [/* original payload */],
]
```

---

## Event Types

| Normalized event | Raw Zalo event | Normalized message |
|---|---|---|
| `user_text` | `message.text.received` | `['text' => string]` |
| `user_image` | `message.image.received` | `['photo' => array, 'caption' => ?string]` |
| `user_sticker` | `message.sticker.received` | `['sticker' => array]` |
| `user_voice` | `message.voice.received` | `['voiceUrl' => ?string]` |
| `user_unsupported` | `message.unsupported.received` | Raw message array |
| `user_follow` | `user.follow` | Raw message array |
| `user_unfollow` | `user.unfollow` | Raw message array |

### Handling events

```php
switch ($event['event']) {
    case 'user_text':
        $bot->message->sendText($event['chatId'], 'You said: ' . $event['message']['text']);
        break;
    case 'user_follow':
        $bot->message->sendText($event['chatId'], 'Thanks for following! 🎉');
        break;
    case 'user_unfollow':
        error_log('User unfollowed: ' . $event['userId']);
        break;
    case 'user_image':
        error_log('Image received from ' . $event['userId']);
        break;
    case 'user_voice':
        error_log('Voice URL: ' . ($event['message']['voiceUrl'] ?? 'none'));
        break;
    default:
        error_log('Unhandled event: ' . $event['event']);
}
```

---

## Best Practices

1. **Always verify the secret token** before parsing or processing a request.
2. **Respond within 5 seconds**; queue expensive work.
3. **Handle all event types** and log unknown events for forward compatibility.
4. **Make handlers idempotent** because duplicate deliveries can occur.
5. **Use normalized `event` names** while retaining `eventName` for diagnostics.
6. **Log event, userId, and messageId** without logging secrets.
7. **Catch handler exceptions** so a transient application error does not trigger unwanted retries.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| `403 Invalid webhook secret token` | Check `ZALO_BOT_SECRET` and header spelling/case |
| Missing `event_name` | Pass the decoded JSON object, not the raw JSON string |
| Webhook URL unreachable | Use a public HTTPS endpoint and check firewall/DNS |
| Timeout | Return 200 immediately and process asynchronously |
| Duplicate events | Make processing idempotent using `messageId` |
