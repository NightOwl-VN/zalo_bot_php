# Zalo Bot PHP SDK

[![Latest Stable Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/v)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![Latest Unstable Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/v/unstable)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![License](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/license)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![Total Downloads](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/downloads)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)

A lightweight, modular PHP SDK for the [Zalo Bot Platform API](https://bot.zapps.me/docs/).
Written in PHP 8.1+ with strict types, PSR-18 HTTP client support, and zero framework coupling.

Port of the Node.js [zalobot-sdk](https://github.com/NightOwl-VN/zalobot-sdk).

## Installation

```bash
composer require hoangkhacphuc/zalobot-sdk
```

## Requirements

- PHP >= 8.1
- A PSR-18 HTTP client (e.g. `guzzlehttp/guzzle` for the default)

## Quick Start

### 1. Setup

```php
<?php

require 'vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

$bot = ZaloBot::fromEnv([
    'ZALO_BOT_TOKEN' => 'your_bot_token_here',
    'ZALO_BOT_SECRET' => 'your_secret_here',
]);
```

### 2. Send Messages

```php
// Text
$bot->message->sendText('user_chat_id', 'Hello from PHP SDK!');

// Photo
$bot->message->sendPhoto('chat_id', 'https://example.com/photo.jpg', [
    'caption' => 'Nice photo!',
]);

// Sticker
$bot->message->sendSticker('chat_id', 'sticker-id-from-zalo');

// Voice (1-1 only)
$bot->message->sendVoice('user_id', 'https://example.com/voice.aac');
```

### 3. Handle Webhook

```php
<?php

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Modules\WebhookModule;

$webhook = new WebhookModule(secretKey: 'your_secret');

// In your webhook endpoint:
$event = $webhook->parseEvent(json_decode(file_get_contents('php://input'), true));

if ($event['event'] === 'user_text') {
    $bot->message->sendText($event['chatId'], 'You said: ' . $event['message']['text']);
}
```

### 4. Get User Profile

```php
$user = $bot->user->getProfile('user_id');
echo $user['name'];
echo $user['avatar'];

// With cache (5 minutes TTL)
$user = $bot->user->getProfileCached('user_id');
```

### 5. Media Upload

```php
// Upload image
$result = $bot->media->uploadImage('/path/to/photo.jpg');
$attachmentId = $result['attachment_id'];

// Upload file
$result = $bot->media->uploadFile('/path/to/document.pdf');
```

## API Reference

### `ZaloBot` (Main class)

| Method | Description |
|--------|-------------|
| `ZaloBot::fromEnv(array $overrides)` | Create from env vars |
| `->getConfig()` | Safe config (masks token) |
| `->setBotToken(string)` | Update token at runtime |

### `MessageModule` (`$bot->message`)

| Method | Description |
|--------|-------------|
| `sendText($chatId, $text, $options)` | Send text |
| `sendPhoto($chatId, $url, $options)` | Send photo |
| `sendSticker($chatId, $stickerId)` | Send sticker |
| `sendVoice($chatId, $url)` | Send voice (1-1 only) |
| `sendChatAction($chatId, $action)` | Send typing indicator |
| `getMe()` | Get bot info |
| `getUpdates($timeout)` | Long poll (no webhook) |
| `setWebhook($url, $secret)` | Register webhook |
| `testWebhook()` | Test webhook |
| `deleteWebhook()` | Delete webhook |
| `getWebhookInfo()` | Get webhook info |

### `UserModule` (`$bot->user`)

| Method | Description |
|--------|-------------|
| `getProfile($userId, $options)` | Get user profile |
| `getFollowers($params)` | List followers |
| `isFollowing($userId)` | Check if following |
| `getProfileCached($userId)` | Cached profile (5min) |
| `clearCache(?$userId)` | Clear cache |

### `WebhookModule`

| Method | Description |
|--------|-------------|
| `verify($headers)` | Verify secret token |
| `parseEvent($payload)` | Parse & normalize event (returns array) |
| `parseEventDto($payload)` | Parse & return typed `WebhookEvent` DTO |
| `handle($callback)` | Execute handler |
| `EVENT_MAP` | Raw → normalized event names |

The `WebhookEvent` DTO offers named properties (`->event`, `->userId`, `->chatId`, etc.)
plus convenience helpers (`->isText()`, `->isFollow()`) and is fully array-accessible
for backward compatibility.

### `MediaModule` (`$bot->media`)

| Method | Description |
|--------|-------------|
| `uploadImage($path)` | Upload image |
| `uploadFile($path)` | Upload file |
| `getMediaUrl($id)` | Get media URL |
| `downloadMedia($id, $path)` | Download to disk |

## Error Handling

All errors extend `ZaloBotException`:

| Exception | When |
|-----------|------|
| `ApiException` | API error response |
| `AuthException` | Invalid/expired token |
| `RateLimitException` | HTTP 429 |
| `WebhookException` | Secret mismatch |
| `ValidationException` | Input validation |
| `NetworkException` | Connection failure |
| `TimeoutException` | Request timeout |

```php
use ZaloBot\Sdk\Exceptions\RateLimitException;

try {
    $bot->message->sendText($chatId, $text);
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 1);
    // retry
}
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ZALO_BOT_TOKEN` | *required* | Bot token from bot.zapps.me |
| `ZALO_BOT_SECRET` | *null* | Webhook secret (8-256 chars) |
| `ZALO_BOT_TIMEOUT` | `30000` | Request timeout (ms) |
| `ZALO_BOT_MAX_RETRIES` | `3` | Retry attempts on 429 |
| `ZALO_BOT_BASE_URL` | `https://bot-api.zaloplatforms.com` | API base |

## License

MIT
