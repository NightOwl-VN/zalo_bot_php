# Tham chiếu API

Tài liệu tham chiếu API đầy đủ cho Zalo Bot PHP SDK.

> **Zalo Bot API Base URL:** `https://bot-api.zaloplatforms.com/bot{BOT_TOKEN}/{method}`
> **Xác thực:** Bot Token nhúng trong URL path (không phải trong header)
> **Reference:** https://bot.zapps.me/docs/

---

## Mục lục

- [Cấu hình](#cấu-hình)
- [ZaloBot](#zalobot)
- [ZaloClient](#zaloclient)
- [MessageModule](#messagemodule)
- [UserModule](#usermodule)
- [WebhookModule](#webhookmodule)
- [MediaModule](#mediamodule)
- [Xử lý lỗi](#xử-lý-lỗi)

---

## Cấu hình

Zalo Bot sử dụng xác thực **Bot Token**. Token được nhúng trong API URL:

```
https://bot-api.zaloplatforms.com/bot{BOT_TOKEN}/{method}
```

**Ví dụ:**
```
https://bot-api.zaloplatforms.com/bot123456789:abc-xyz/sendMessage
```

**Bot Token:**
- Định dạng: `123456789:abc-xyz`
- Nhận được sau khi tạo bot qua Zalo Bot Creator
- Không hết hạn cho đến khi được reset thủ công
- Reset: Mở Zalo Bot Creator → Settings → Reset Token

---

## ZaloBot

Lớp chính làm điểm truy cập cho tất cả các tính năng SDK.

```php
use ZaloBot\Sdk\ZaloBot;

// Từ mảng cấu hình
$bot = new ZaloBot([
    'botToken' => '123456789:abc-xyz',
    'secretKey' => 'your_secret_key',     // không bắt buộc
    'timeout' => 30000,                    // không bắt buộc, ms (mặc định: 30000)
    'maxRetries' => 3,                     // không bắt buộc (mặc định: 3)
    'baseURL' => 'https://bot-api.zaloplatforms.com', // không bắt buộc
]);

// Từ đối tượng Config
use ZaloBot\Sdk\Config;
$bot = new ZaloBot(Config::fromEnv());

// Từ biến môi trường
$bot = ZaloBot::fromEnv();
```

| Phương thức | Mô tả |
|-------------|--------|
| `ZaloBot::fromEnv(array $overrides = [])` | Tạo bot từ biến môi trường |
| `$bot->setBotToken(string $newToken)` | Cập nhật token khi chạy |
| `$bot->getConfig(bool $includeSecrets = false, bool $fullToken = false)` | Array cấu hình an toàn (mask token) |
| `$bot->message` | MessageModule instance |
| `$bot->user` | UserModule instance |
| `$bot->webhook` | WebhookModule instance |
| `$bot->media` | MediaModule instance |
| `$bot->client` | ZaloClient instance |
| `$bot->config` | Config instance |

### Config

| Phương thức | Mô tả |
|-------------|--------|
| `Config::fromEnv(array $overrides = [])` | Tạo config từ biến môi trường |
| `$config->getBotToken()` | Bot token đầy đủ |
| `$config->getMaskedToken()` | Bot token bị mask (ví dụ: `123456...`) |
| `$config->hasSecretKey()` | Có secret key hợp lệ (≥ 8 ký tự) không |
| `$config->toArray(bool $includeSecrets = false, bool $fullToken = false)` | Config dạng array (mask mặc định) |

**Biến môi trường:**

| Biến | Bắt buộc | Mặc định | Mô tả |
|------|----------|----------|-------|
| `ZALO_BOT_TOKEN` | Có | — | Bot Token |
| `ZALO_BOT_SECRET` | Không | `null` | Secret Key cho xác minh webhook |
| `ZALO_BOT_TIMEOUT` | Không | `30000` | Timeout request (ms) |
| `ZALO_BOT_MAX_RETRIES` | Không | `3` | Số lần thử lại khi bị rate limit (429) |
| `ZALO_BOT_BASE_URL` | Không | `https://bot-api.zaloplatforms.com` | Base URL API |

---

## ZaloClient

Client HTTP dựa trên Guzzle 7 với tự động retry khi bị rate limit (429) và xử lý lỗi API.

| Phương thức | Mô tả |
|-------------|--------|
| `$client->get(string $method, array $params = [])` | Gửi GET request |
| `$client->post(string $method, array $data = [])` | Gửi POST request (JSON body) |
| `$client->upload(string $method, array $formData)` | Gửi multipart upload request |
| `$client->updateBotToken(string $newToken)` | Cập nhật token khi chạy |
| `$client->getBotToken()` | Token hiện tại |
| `$client->getRequestBaseUrl()` | Base URL đầy đủ với token nhúng |

---

## MessageModule

Gửi và quản lý tin nhắn Zalo Bot. Truy cập qua `$bot->message`.

| Phương thức | Mô tả |
|-------------|--------|
| `sendText(string $chatId, string $text, array $options = [])` | Gửi tin nhắn văn bản (1–2000 ký tự) |
| `sendPhoto(string $chatId, string $photoUrl, array $options = [])` | Gửi ảnh bằng URL |
| `sendSticker(string $chatId, string $stickerId)` | Gửi sticker |
| `sendVoice(string $chatId, string $voiceUrl)` | Gửi tin nhắn thoại (chỉ chat 1-1) |
| `sendChatAction(string $chatId, string $action)` | Hiển thị chỉ báo đang nhập (`typing` / `upload_photo`) |
| `getMe()` | Lấy thông tin bot |
| `getUpdates(int $timeout = 30)` | Lấy cập nhật bằng long polling (chỉ khi không có webhook) |
| `setWebhook(string $url, string $secretToken)` | Đăng ký URL webhook (secret token 8–256 ký tự) |
| `testWebhook()` | Kiểm tra kết nối webhook |
| `deleteWebhook()` | Xóa webhook (chuyển về polling) |
| `getWebhookInfo()` | Lấy trạng thái webhook hiện tại |

**Ví dụ:**

```php
// Gửi văn bản
$bot->message->sendText('chat_id', 'Xin chào từ PHP SDK!');

// Gửi ảnh với chú thích
$bot->message->sendPhoto('chat_id', 'https://example.com/photo.jpg', [
    'caption' => 'Ảnh đẹp!',
]);

// Gửi sticker
$bot->message->sendSticker('chat_id', 'sticker-id-from-zalo');

// Gửi tin nhắn thoại (chỉ chat 1-1)
$bot->message->sendVoice('user_id', 'https://example.com/voice.aac');

// Chỉ báo đang nhập
$bot->message->sendChatAction('chat_id', 'typing');

// Thông tin bot
$info = $bot->message->getMe();
echo $info['result']['id'];

// Quản lý webhook
$bot->message->setWebhook('https://your-domain.com/webhook', 'your-secret-token');
$bot->message->testWebhook();
$info = $bot->message->getWebhookInfo();
$bot->message->deleteWebhook();
```

---

## UserModule

Lấy thông tin người dùng và quản lý follower. Truy cập qua `$bot->user`.

| Phương thức | Mô tả |
|-------------|--------|
| `getProfile(string $userId, array $options = [])` | Lấy hồ sơ người dùng |
| `getFollowers(array $params = [])` | Liệt kê follower (limit, cursor, fields) |
| `isFollowing(string $userId)` | Kiểm tra người dùng có theo dõi bot không |
| `getProfileCached(string $userId, array $options = [])` | Lấy hồ sơ với cache 5 phút |
| `clearCache(?string $userId = null)` | Xóa cache |

**Ví dụ:**

```php
// Hồ sơ người dùng
$user = $bot->user->getProfile('user_id');
echo $user['result']['name'];

// Danh sách follower (phân trang)
$followers = $bot->user->getFollowers(['limit' => 50, 'cursor' => $cursor]);

// Kiểm tra trạng thái theo dõi
$isFollowing = $bot->user->isFollowing('user_id');

// Hồ sơ cache (TTL 5 phút, LRU eviction)
$user = $bot->user->getProfileCached('user_id');

// Xóa cache
$bot->user->clearCache('user_id');
$bot->user->clearCache(); // tất cả
```

---

## WebhookModule

Phân tích và xác minh sự kiện webhook Zalo Bot. Truy cập qua `$bot->webhook`.

| Phương thức | Mô tả |
|-------------|--------|
| `verify(array\|object $headers)` | Kiểm tra an toàn timing-safe header `X-Bot-Api-Secret-Token` |
| `requireValid(array\|object $headers)` | Xác minh hoặc ném `WebhookException` (403) |
| `parseEvent(array $payload)` | Phân tích payload thành sự kiện chuẩn hóa |
| `handle(callable $handler, ?array $headers = null, ?array $body = null)` | Xác minh → phân tích → gọi handler |

**Hằng số EVENT_MAP:**

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

---

## MediaModule

Tải lên và quản lý file media. Truy cập qua `$bot->media`.

| Phương thức | Mô tả |
|-------------|--------|
| `uploadImage(string $filePath, array $options = [])` | Upload file ảnh cục bộ |
| `uploadFile(string $filePath, array $options = [])` | Upload file bất kỳ |
| `getMediaUrl(string $attachmentId, bool $redirect = false)` | Lấy URL từ attachment ID |
| `downloadMedia(string $attachmentId, string $savePath)` | Tải media xuống đường dẫn cục bộ |

> 🔒 **Bảo mật:** `getMediaUrl`/`downloadMedia` chặn host nội bộ/private để ngăn tấn công SSRF.

---

## Xử lý lỗi

Tất cả exception đều kế thừa `ZaloBot\Sdk\Exceptions\ZaloBotException`.

| Exception | Khi nào | Truy cập thêm |
|-----------|---------|----------------|
| `ZaloBotException` | Lớp cơ sở | `getApiErrorCode()`, `getHttpStatus()`, `getDetails()` |
| `ApiException` | API trả về `ok: false` hoặc lỗi HTTP | kế thừa |
| `AuthException` | HTTP 401/403 — token không hợp lệ | kế thừa |
| `RateLimitException` | HTTP 429 rate limit | `getRetryAfter()` |
| `WebhookException` | Xác minh secret token webhook thất bại | kế thừa |
| `ValidationException` | Đầu vào không hợp lệ | `getField()` |
| `NetworkException` | Lỗi DNS / kết nối | kế thừa |
| `TimeoutException` | Request hết thời gian chờ | kế thừa |

**Ví dụ:**

```php
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\RateLimitException;

try {
    $bot->message->sendText('invalid_user', 'Xin chào');
} catch (AuthException $e) {
    error_log('Xác thực thất bại: ' . $e->getMessage());
} catch (RateLimitException $e) {
    error_log('Giới hạn tần suất. Thử lại sau: ' . ($e->getRetryAfter() ?? '') . 's');
} catch (ApiException $e) {
    error_log("Lỗi API {$e->getApiErrorCode()}: {$e->getMessage()}");
} catch (\Throwable $e) {
    error_log('Lỗi không xác định: ' . $e->getMessage());
}
```
