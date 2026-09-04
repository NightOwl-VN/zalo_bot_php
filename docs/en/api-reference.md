# API Reference

Complete API reference for the Zalo Bot PHP SDK.

> **Zalo Bot API Base URL:** `https://bot-api.zaloplatforms.com/bot{BOT_TOKEN}/{method}`
> **Authentication:** Bot Token embedded in URL path (not in headers)
> **Reference:** https://bot.zapps.me/docs/

---

## Table of Contents

- [Configuration](#configuration)
- [ZaloBot](#zalobot)
- [ZaloClient](#zaloclient)
- [MessageModule](#messagemodule)
- [UserModule](#usermodule)
- [WebhookModule](#webhookmodule)
- [MediaModule](#mediamodule)
- [Exceptions](#exceptions)

---

## Configuration

Zalo Bot uses **Bot Token** authentication. The token is embedded in the API URL:

```
https://bot-api.zaloplatforms.com/bot{BOT_TOKEN}/{method}
```

**Example:**
```
https://bot-api.zaloplatforms.com/bot123456789:abc-xyz/sendMessage
```

**Bot Token:**
- Format: `123456789:abc-xyz`
- Obtained after creating a bot via Zalo Bot Creator
- Does not expire until manually reset
- Reset: Open Zalo Bot Creator → Settings → Reset Token

---

## ZaloBot

The main entry point for all SDK features.

```php
use ZaloBot\Sdk\ZaloBot;

// From a configuration array
$bot = new ZaloBot([
    'botToken' => '123456789:abc-xyz',
    'secretKey' => 'your_secret_key',     // optional, for webhook verification
    'timeout' => 30000,                    // optional, milliseconds (default: 30000)
    'maxRetries' => 3,                     // optional (default: 3)
    'baseURL' => 'https://bot-api.zaloplatforms.com', // optional
]);

// From a Config object
use ZaloBot\Sdk\Config;
$bot = new ZaloBot(Config::fromEnv());

// From environment variables
$bot = ZaloBot::fromEnv();
```

| Method | Description |
|--------|-------------|
| `ZaloBot::fromEnv(array $overrides = [])` | Create bot from environment variables |
| `$bot->setBotToken(string $newToken)` | Update bot token at runtime |
| `$bot->getConfig(bool $includeSecrets = false, bool $fullToken = false)` | Safe config array (token masked by default) |
| `$bot->message` | MessageModule instance |
| `$bot->user` | UserModule instance |
| `$bot->webhook` | WebhookModule instance |
| `$bot->media` | MediaModule instance |
| `$bot->client` | ZaloClient instance |
| `$bot->config` | Config instance |

### Config

| Method | Description |
|--------|-------------|
| `Config::fromEnv(array $overrides = [])` | Create config from environment variables |
| `$config->getBotToken()` | Full bot token |
| `$config->getMaskedToken()` | Masked bot token (e.g. `123456...`) |
| `$config->hasSecretKey()` | Whether a valid secret key (≥ 8 chars) is set |
| `$config->toArray(bool $includeSecrets = false, bool $fullToken = false)` | Config as array (masked by default) |

**Environment variables:**

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `ZALO_BOT_TOKEN` | Yes | — | Bot Token |
| `ZALO_BOT_SECRET` | No | `null` | Secret Key for webhook verification |
| `ZALO_BOT_TIMEOUT` | No | `30000` | Request timeout in milliseconds |
| `ZALO_BOT_MAX_RETRIES` | No | `3` | Retry attempts on rate limit (429) |
| `ZALO_BOT_BASE_URL` | No | `https://bot-api.zaloplatforms.com` | API base URL |

---

## ZaloClient

The HTTP client wrapping Guzzle 7 with automatic retry on rate limits (429) and API error handling.

| Method | Description |
|--------|-------------|
| `$client->get(string $method, array $params = [])` | Send GET request |
| `$client->post(string $method, array $data = [])` | Send POST request (JSON body) |
| `$client->upload(string $method, array $formData)` | Send multipart upload request |
| `$client->updateBotToken(string $newToken)` | Update token at runtime |
| `$client->getBotToken()` | Current bot token |
| `$client->getRequestBaseUrl()` | Full base URL with embedded token |

---

## MessageModule

Send and manage Zalo Bot messages. Accessible via `$bot->message`.

| Method | Description |
|--------|-------------|
| `sendText(string $chatId, string $text, array $options = [])` | Send text message (1–2000 chars) |
| `sendPhoto(string $chatId, string $photoUrl, array $options = [])` | Send image by URL |
| `sendSticker(string $chatId, string $stickerId)` | Send sticker (ID from stickers.zaloapp.com) |
| `sendVoice(string $chatId, string $voiceUrl)` | Send voice message (.aac URL, 1-1 chats only) |
| `sendChatAction(string $chatId, string $action)` | Show typing indicator (`typing` / `upload_photo`) |
| `getMe()` | Get bot info — validates the token |
| `getUpdates(int $timeout = 30)` | Long-poll for updates (only without webhook) |
| `setWebhook(string $url, string $secretToken)` | Register webhook URL (8–256 char secret token) |
| `testWebhook()` | Test current webhook connectivity |
| `deleteWebhook()` | Remove webhook (switch back to polling) |
| `getWebhookInfo()` | Get current webhook status |

**Examples:**

```php
// Text
$bot->message->sendText('chat_id', 'Hello from PHP SDK!');

// Photo with caption
$bot->message->sendPhoto('chat_id', 'https://example.com/photo.jpg', [
    'caption' => 'Nice photo!',
]);

// Sticker
$bot->message->sendSticker('chat_id', 'sticker-id-from-zalo');

// Voice (1-1 chats only)
$bot->message->sendVoice('user_id', 'https://example.com/voice.aac');

// Typing indicator
$bot->message->sendChatAction('chat_id', 'typing');

// Bot info
$info = $bot->message->getMe();
echo $info['result']['id'];

// Webhook management
$bot->message->setWebhook('https://your-domain.com/webhook', 'your-secret-token');
$bot->message->testWebhook();
$info = $bot->message->getWebhookInfo();
$bot->message->deleteWebhook();
```

**Validation errors (ValidationException):**

| Rule | Field |
|------|-------|
| Empty `chatId` | `chatId` |
| Empty `text` or > 2000 characters | `text` |
| Invalid `photoUrl` / `voiceUrl` | `photo` / `voice_url` |
| `secretToken` not 8–256 chars | `secretToken` |
| Non-HTTPS webhook `url` | `url` |

---

## UserModule

Get user information and follower management. Accessible via `$bot->user`.

| Method | Description |
|--------|-------------|
| `getProfile(string $userId, array $options = [])` | Get user profile |
| `getFollowers(array $params = [])` | List followers (limit, cursor, fields) |
| `isFollowing(string $userId)` | Check if user follows the bot |
| `getProfileCached(string $userId, array $options = [])` | Get profile with 5-min TTL cache |
| `clearCache(?string $userId = null)` | Clear cache (all, or one user) |

**Examples:**

```php
// User profile
$user = $bot->user->getProfile('user_id');
echo $user['result']['name'];

// List followers (paginated)
$followers = $bot->user->getFollowers(['limit' => 50, 'cursor' => $cursor]);

// Check follow status
$isFollowing = $bot->user->isFollowing('user_id');

// Cached profile (5-minute TTL, LRU eviction)
$user = $bot->user->getProfileCached('user_id');

// Invalidate cache
$bot->user->clearCache('user_id');
$bot->user->clearCache(); // everything
```

---

## WebhookModule

Parse and verify Zalo Bot webhook events. Accessible via `$bot->webhook`.

| Method | Description |
|--------|-------------|
| `verify(array|object $headers)` | Timing-safe check of `X-Bot-Api-Secret-Token` header |
| `requireValid(array|object $headers)` | Verify or throw `WebhookException` (403) |
| `parseEvent(array $payload)` | Parse wrapped/flat payload into normalized event |
| `handle(callable $handler, ?array $headers = null, ?array $body = null)` | Verify → parse → invoke callback |

**Constants:**

```php
WebhookModule::EVENT_MAP = [
    'message.text.received' => 'user_text',
    'message.image.received' => 'user_image',
    'message.sticker.received' => 'user_sticker',
    'message.voice.received' => 'user_voice',
    'message.unsupported.received' => 'user_unsupported',
    'user.follow' => 'user_follow',
    'user.unfollow' => 'user_unfollow',
];
```

**Example:**

```php
$event = $bot->webhook->parseEvent(json_decode($requestBody, true));
// [
//   'event' => 'user_text',
//   'eventName' => 'message.text.received',
//   'userId' => '123456789',
//   'chatId' => '123456789',
//   'messageId' => 'msg_xyz',
//   'timestamp' => 1623456789,
//   'message' => ['text' => 'Hello bot!'],
//   'raw' => [ ... original payload ... ],
// ]
```

---

## MediaModule

Upload and manage media files. Accessible via `$bot->media`.

| Method | Description |
|--------|-------------|
| `uploadImage(string $filePath, array $options = [])` | Upload a local image file |
| `uploadFile(string $filePath, array $options = [])` | Upload any local file |
| `getMediaUrl(string $attachmentId, bool $redirect = false)` | Resolve attachment ID to URL |
| `downloadMedia(string $attachmentId, string $savePath)` | Download attachment to local path |

**Examples:**

```php
// Upload
$result = $bot->media->uploadImage('/path/to/photo.jpg');
$attachmentId = $result['result']['attachment_id'];

// Resolve URL
$url = $bot->media->getMediaUrl('attachment_id');

// Download
$savedTo = $bot->media->downloadMedia('attachment_id', '/tmp/photo.jpg');
```

> 🔒 **Security:** `getMediaUrl`/`downloadMedia` reject private/internal hosts (127.x, 10.x, 172.16–31.x, 192.168.x, 169.254.x, localhost, ::1) to prevent SSRF attacks.

---

## Exceptions

All exceptions extend `ZaloBot\Sdk\Exceptions\ZaloBotException`, which extends PHP's `\Exception`.

| Exception | Thrown when | Extra accessors |
|-----------|-------------|-----------------|
| `ZaloBotException` | Base class | `getApiErrorCode()`, `getHttpStatus()`, `getDetails()` |
| `ApiException` | API returns `ok: false` or HTTP error | inherits |
| `AuthException` | HTTP 401/403 — invalid or expired bot token | inherits |
| `RateLimitException` | HTTP 429 rate limited | `getRetryAfter()` |
| `WebhookException` | Webhook secret token verification failed | inherits |
| `ValidationException` | Invalid method input | `getField()` |
| `NetworkException` | DNS / connection failures | inherits |
| `TimeoutException` | Request timed out | inherits |

**Example:**

```php
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\ValidationException;

try {
    $bot->message->sendText('invalid_user', 'Hello');
} catch (ValidationException $e) {
    echo "Invalid field: {$e->getField()}";
} catch (AuthException $e) {
    echo 'Authentication failed: ' . $e->getMessage();
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: {$e->getRetryAfter()}s";
} catch (ApiException $e) {
    echo "API Error {$e->getApiErrorCode()}: {$e->getMessage()}";
}
```

---

## Response Format

All Zalo Bot API responses return JSON in this format:

```json
{
  "ok": true,
  "result": { ... }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `ok` | boolean | `true` if the request succeeded |
| `result` | object | Data returned by the API |
| `error_code` | number | Error code (only present when `ok` is false) |
| `description` | string | Error description (only present when `ok` is false) |

The SDK automatically throws an exception when `ok` is `false` — you never need to check it manually.
