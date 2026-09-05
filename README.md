# Zalo Bot PHP SDK

[![Latest Stable Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/v)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![Latest Unstable Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/v/unstable)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![License](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/license)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![Total Downloads](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/downloads)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)

A lightweight, modular PHP SDK for the [Zalo Bot Platform API](https://bot.zapps.me/docs/).
Written in PHP 8.1+ with strict types, PSR-18 HTTP client support, and zero framework coupling.

Port of the Node.js [zalobot-sdk](https://github.com/NightOwl-VN/zalobot-sdk).

> **⚠️ Status:** This SDK is in pre-release development. The public API is stabilizing
> but not yet tagged as 1.0.0. Pin to a specific commit and pin your Guzzle dependency
> until a first stable release is announced.

## Installation

```bash
composer require hoangkhacphuc/zalobot-sdk
```

## Requirements

- PHP >= 8.1
- A PSR-18 HTTP client — `guzzlehttp/guzzle` (^7.0 || ^8.0) is the default

## Quick Start

### 1. Setup — Guzzle (default)

```php
<?php
require 'vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

// Default: uses Guzzle 7/8 as the HTTP client.
$bot = ZaloBot::fromEnv([
    'ZALO_BOT_TOKEN' => 'your_bot_token_here',
    'ZALO_BOT_SECRET' => 'your_secret_here',
]);
```

### 2. Setup — Custom PSR-18 Client

Inject any PSR-18-compliant client as the second constructor argument:

```php
use ZaloBot\Sdk\ZaloBot;

$myClient = new MyCustomPsr18Client(); // implements ClientInterface

$bot = new ZaloBot([
    'botToken' => 'your_bot_token_here',
    'secretKey' => 'your_secret_here',
], $myClient);
```

The `ZaloClient` can also be used directly:

```php
use ZaloBot\Sdk\ZaloClient;

$client = new ZaloClient('your_token', httpClient: $myClient, retryDelayMs: 0);
$result = $client->get('getMe');
```

### 3. Send Messages

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

### 4. Handle Webhook

```php
<?php
use ZaloBot\Sdk\Modules\WebhookModule;

$webhook = new WebhookModule(secretKey: 'your_secret');

// parseEvent() returns a plain array (backward compatible):
$event = $webhook->parseEvent(json_decode(file_get_contents('php://input'), true));
if ($event['event'] === 'user_text') {
    $bot->message->sendText($event['chatId'], 'You said: ' . $event['message']['text']);
}

// parseEventDto() returns a typed WebhookEvent value object:
$dto = $webhook->parseEventDto(json_decode(file_get_contents('php://input'), true));
if ($dto->isText()) {
    $bot->message->sendText($dto->chatId, 'DTO: ' . $dto->message['text']);
}
// $dto->event, $dto->userId, $dto->chatId, $dto->messageId, $dto->timestamp, $dto->raw
// $dto['event'] works too (ArrayAccess, immutable — set/unset throw)
```

### 5. Get User Profile

```php
$user = $bot->user->getProfile('user_id');
echo $user['name'];

// Cached profile: 5-minute TTL, LRU eviction at 1000 entries.
// Cache hits update recency so frequently-read profiles stay.
$user = $bot->user->getProfileCached('user_id');

// Force refresh cache for one user:
$bot->user->getProfileCached('user_id', ['forceRefresh' => true]);

// Clear cache for one user, or all users:
$bot->user->clearCache('user_id');
$bot->user->clearCache(); // all
```

### 6. Media Upload

```php
// Upload image
$result = $bot->media->uploadImage('/path/to/photo.jpg');
$attachmentId = $result['attachment_id'];

// Upload file
$result = $bot->media->uploadFile('/path/to/document.pdf');
```

### 7. Media Download

```php
// downloadMedia streams to a temp file, then atomically renames it into place.
// Redirects are rejected — the download URL is validated against SSRF rules.
$bot->media->downloadMedia('attachment_id', '/tmp/save/image.png');
```

## Retry Policy

Automatic retry is **read-only safe by default**: only `GET` requests are retried.
`POST`/`PUT`/upload requests are **never** auto-retried because mutating operations
are not inherently idempotent.

To opt in to mutating-request retries (only use this if your operations are
idempotent, e.g. retries with identical payloads):

```php
$client = new ZaloClient('token', retryMutations: true);
```

| Status | Retried? |
|--------|----------|
| 408, 429, 502, 503, 504 | ✅ yes (GET/read only) |
| 400, 401, 403, 404, 422 | ❌ never |
| Network connection failure | ✅ yes (GET/read only, not a timeout) |

Backoff uses exponential delay with jitter and respects the server `Retry-After`
header (seconds or HTTP date). Guzzle timeouts (`errno` 28 / `timed_out` context)
are mapped to `TimeoutException` and are **not** retried.

## Download Redirect Policy

`MediaModule::downloadMedia()` does **not** automatically follow HTTP redirects.
A 3xx response is treated as a failure, because the redirect target may be a host
that never passed the SSRF URL validation. If your PSR-18 client (e.g. Guzzle's
default) follows redirects transparently, this method may still succeed — audit
your client's redirect policy accordingly.

## Error Handling

All errors extend `ZaloBotException`:

| Exception | When |
|-----------|------|
| `ApiException` | API error response or malformed JSON from server |
| `AuthException` | Invalid/expired token (401/403) |
| `RateLimitException` | HTTP 429 (use `$e->getRetryAfter()` for seconds) |
| `WebhookException` | Secret mismatch |
| `ValidationException` | Input validation or URL/SSRF rejection |
| `NetworkException` | Connection failure (transport error from PSR-18 client) |
| `TimeoutException` | Request timeout (Guzzle context-based detection) |

> Programming errors (`TypeError`, `Error`, `LogicException`,
> `InvalidArgumentException`, `RuntimeException` from a broken mock or
> driver) are **never** caught and translated — they propagate unchanged
> so bugs surface immediately.

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
| `ZALO_BOT_MAX_RETRIES` | `3` | Retry attempts on 429 / transient errors |
| `ZALO_BOT_BASE_URL` | `https://bot-api.zaloplatforms.com` | API base |

## Development

```bash
# Run the full quality gate (test + phpstan + cs-check):
composer check

# Individual scripts:
composer test
composer phpstan
composer cs-check
composer validate --strict

# Consumer smoke test (path repo, no network):
bash scripts/consumer-smoke.sh
```

## License

MIT
