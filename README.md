# ZaloBot SDK for PHP

[![Latest Stable Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/v)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![License](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/license)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![Total Downloads](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/downloads)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![PHP Version](https://poser.pugx.org/hoangkhacphuc/zalobot-sdk/require/php)](https://packagist.org/packages/hoangkhacphuc/zalobot-sdk)
[![CI](https://github.com/NightOwl-VN/zalobot-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/NightOwl-VN/zalobot-sdk-php/actions/workflows/ci.yml)

Lightweight, modular PHP SDK for the [Zalo Bot Platform API](https://bot.zapps.me/docs/).
Zero framework coupling, PSR-18 HTTP client support, strict types (PHP 8.1+).

Port of the Node.js [zalobot-sdk](https://github.com/NightOwl-VN/zalobot-sdk).

## Installation

```bash
composer require hoangkhacphuc/zalobot-sdk
```

## Requirements

- PHP >= 8.1
- PSR-18 HTTP Client (`guzzlehttp/guzzle` ^7.0 || ^8.0 is included as default)

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

// Default: Guzzle 7/8 as HTTP client, configured via environment variables.
$bot = ZaloBot::fromEnv([
    'ZALO_BOT_TOKEN' => $_ENV['ZALO_BOT_TOKEN'],
    'ZALO_BOT_SECRET' => $_ENV['ZALO_BOT_SECRET'] ?? null,
]);

$result = $bot->message->sendText('user_chat_id', 'Hello from PHP SDK!');
```

### Custom PSR-18 Client

Inject your own PSR-18 client — no Guzzle dependency required on your side:

```php
use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\ZaloClient;

// Any PSR-18 ClientInterface implementation works.
$myClient = new MyCustomHttpClient();

$bot = new ZaloBot([
    'botToken'  => $_ENV['ZALO_BOT_TOKEN'],
    'secretKey' => $_ENV['ZALO_BOT_SECRET'] ?? null,
], $myClient);

// Or use ZaloClient directly for lower-level control:
$client = new ZaloClient($_ENV['ZALO_BOT_TOKEN'], httpClient: $myClient);
$result = $client->get('getMe');
```

## Sending Messages

```php
// Text
$bot->message->sendText('chat_id', 'Hello!');

// Photo
$bot->message->sendPhoto('chat_id', 'https://example.com/photo.jpg', [
    'caption' => 'Nice photo!',
]);

// Sticker
$bot->message->sendSticker('chat_id', 'sticker-id-from-zalo');

// Voice (1:1 only)
$bot->message->sendVoice('user_id', 'https://example.com/voice.aac');
```

## Webhook Handling

```php
use ZaloBot\Sdk\Modules\WebhookModule;

$webhook = new WebhookModule(secretKey: $_ENV['ZALO_BOT_SECRET']);

// parseEvent() — backward-compatible plain array:
$raw    = json_decode(file_get_contents('php://input'), true);
$event  = $webhook->parseEvent($raw);

if ($event['event'] === 'user_text') {
    $bot->message->sendText($event['chatId'], 'You said: ' . $event['message']['text']);
}

// parseEventDto() — typed, immutable WebhookEvent value object:
$dto = $webhook->parseEventDto($raw);
if ($dto->isText()) {
    $bot->message->sendText($dto->chatId, 'DTO: ' . $dto->message['text']);
}
// $dto->event, $dto->userId, $dto->chatId, $dto->messageId, $dto->timestamp, $dto->raw
// Also implements ArrayAccess: $dto['event'] works and is immutable.
```

## User Profile (with Cache)

```php
$user = $bot->user->getProfile('user_id');
echo $user['name'];

// In-memory LRU cache: 5-min TTL, 1000-entry cap, per PHP process.
$user = $bot->user->getProfileCached('user_id');

// Force refresh:
$bot->user->getProfileCached('user_id', forceRefresh: true);

// Clear cache:
$bot->user->clearCache('user_id');
$bot->user->clearCache(); // all users
```

## Media Upload

```php
$result = $bot->media->uploadImage('/path/to/photo.jpg');
$attachmentId = $result['attachment_id'];

$result = $bot->media->uploadFile('/path/to/document.pdf');
```

## Media Download

```php
// Streams to a temp file, then atomically renames into place.
// Redirects are rejected — download URLs are validated against SSRF rules.
$bot->media->downloadMedia('attachment_id', '/tmp/save/image.png');
```

## Error Handling

All SDK exceptions extend `ZaloBotException`:

| Exception | When |
|-----------|------|
| `ApiException` | API error response or malformed JSON from server |
| `AuthException` | Invalid or expired bot token (HTTP 401/403) |
| `RateLimitException` | HTTP 429 — use `$e->getRetryAfter()` for backoff seconds |
| `WebhookException` | Webhook secret mismatch |
| `ValidationException` | Input validation failure or SSRF URL rejection |
| `NetworkException` | Connection failure (DNS, TLS, transport — from PSR-18 client) |
| `TimeoutException` | Request timeout (detected via Guzzle context, not message parsing) |

> Programming errors (`TypeError`, `Error`, `LogicException`, `InvalidArgumentException`)
> are **never** caught and masked — they propagate unchanged so bugs surface immediately.

```php
use ZaloBot\Sdk\Exceptions\RateLimitException;

try {
    $bot->message->sendText($chatId, $text);
} catch (RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    sleep($retryAfter !== null ? (int) $retryAfter : 1);
    // retry
}
```

## Retry Policy

Automatic retry is **read-only safe by default**: only `GET` requests are retried.
`POST` / `PUT` / upload requests are **never** auto-retried unless you opt in.

| Status | Retried? |
|--------|----------|
| 408, 429, 502, 503, 504 | ✅ yes (GET only by default) |
| Network connection failure | ✅ yes (GET only, transient — not a timeout) |
| 400, 401, 403, 404, 422 | ❌ never |
| Timeout | ❌ never |

To opt in to mutating-request retries (only if your operations are idempotent):

```php
$client = new ZaloClient('token', retryMutations: true);
```

Backoff uses exponential delay with jitter and respects the `Retry-After` header
(seconds or HTTP-date format). Guzzle timeouts are detected via handler context
(`errno` 28 / `timed_out`) and mapped to `TimeoutException` — not retried.

## Download Redirect Policy

`MediaModule::downloadMedia()` does **not** follow HTTP redirects. A 3xx response
is treated as a failure because the redirect target may be a private host that
bypassed the initial SSRF validation.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ZALO_BOT_TOKEN` | *required* | Bot token from bot.zapps.me |
| `ZALO_BOT_SECRET` | `null` | Webhook secret (8–256 chars) |
| `ZALO_BOT_TIMEOUT` | `30000` | Request timeout in milliseconds |
| `ZALO_BOT_MAX_RETRIES` | `3` | Max retry attempts for 429 / transient errors |
| `ZALO_BOT_BASE_URL` | `https://bot-api.zaloplatforms.com` | API base URL |

## Development

```bash
# Clone the repository
git clone https://github.com/NightOwl-VN/zalobot-sdk-php.git
cd zalobot-sdk-php

# Install dev dependencies
composer install

# Full quality gate (test + phpstan + cs-check)
composer check

# Individual checks
composer test
composer phpstan
composer cs-check
composer validate --strict

# Consumer smoke test (path repo, no network)
bash scripts/consumer-smoke.sh
```

## License

MIT — see [LICENSE](LICENSE) for details.
