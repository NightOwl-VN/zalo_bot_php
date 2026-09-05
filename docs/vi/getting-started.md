# Bắt đầu

Hướng dẫn này sẽ hướng dẫn bạn từng bước tạo bot Zalo, cài đặt SDK PHP và triển khai lên môi trường production.

---

## Bước 1: Tạo Bot Zalo

1. Truy cập [Zalo Bot Platform](https://bot.zapps.me/) (đây là nền tảng Bot Zalo, KHÔNG phải Zalo Official Account/OA)
2. Đăng nhập bằng tài khoản Zalo của bạn
3. Nhấp vào **"Tạo Bot"** (Zalo Bot Platform, không phải tài khoản OA)
4. Điền tên bot, mô tả và các trường bắt buộc khác
5. Sau khi tạo, vào trang chi tiết bot
6. Sao chép **Bot Token** và **Secret Key**

> ⚠️ **Quan trọng:** Lưu trữ các thông tin này một cách an toàn. Bot Token dùng để xác thực API, Secret Key dùng để xác minh chữ ký webhook.

---

## Bước 2: Cài đặt SDK

```bash
composer require hoangkhacphuc/zalobot-sdk
```

---

## Bước 3: Cấu hình Biến môi trường

Tạo file `.env` trong thư mục dự án:

```env
ZALO_BOT_TOKEN=your_bot_token_here
ZALO_BOT_SECRET=your_secret_key_here
ZALO_BOT_TIMEOUT=30000             # không bắt buộc
ZALO_BOT_MAX_RETRIES=3             # không bắt buộc
```

> 💡 Sử dụng trình loader `.env` như vlucas/phpdotenv hoặc symfony/dotenv để tự động nạp biến này vào `$_ENV`.

---

## Bước 4: Viết Bot đầu tiên

Tạo file PHP (ví dụ: `index.php`):

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

// Khởi tạo bot — đọc từ .env (load qua vlucas/phpdotenv hoặc tương tự)
$bot = ZaloBot::fromEnv();

// Gửi tin nhắn thử
$bot->message->sendText('USER_ID', 'Xin chào từ Zalo Bot PHP SDK!');
```

---

## Bước 5: Xử lý Webhook (Native PHP)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Modules\WebhookModule;

$bot = ZaloBot::fromEnv();

// Đọc request body
$headers = getallheaders();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Xác minh secret token
if (!$bot->webhook->verify($headers)) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

// Phân tích sự kiện
$event = $bot->webhook->parseEvent($body);

// Xử lý sự kiện
if ($event['event'] === 'user_text') {
    $bot->message->sendText($event['chatId'], "Bạn đã nói: {$event['message']['text']}");
}

// Trả về 200 OK
http_response_code(200);
echo json_encode(['message' => 'Đã OK']);
```

---

## Bước 6: Kiểm tra cục bộ với Tunneling

Để kiểm tra cục bộ, expose endpoint webhook qua công cụ tunneling:

**Dùng ngrok:**
```bash
ngrok http 8080
# Sao chép URL HTTPS và đặt tại Zalo Developer Platform → Webhook
```

**Dùng Cloudflare Tunnel:**
```bash
cloudflared tunnel --url http://localhost:8080
```

Đặt URL được tạo làm endpoint webhook trên Zalo Developer Platform.

---

## Bước 7: Triển khai lên Production

### Lựa chọn A: Triển khai trên Render

1. Push code lên GitHub
2. Tạo Web Service mới trên Render
3. Thiết lập biến môi trường
4. Triển khai

### Lựa chọn B: Triển khai trên Railway

1. Push code lên GitHub
2. Tạo project mới trên Railway
3. Thêm biến môi trường
4. Triển khai

### Lựa chọn C: Triển khai trên VPS

```bash
# Clone repository
git clone https://github.com/NightOwl-VN/zalo_bot_php.git
cd zalo_bot_php

# Cài đặt dependencies
composer install --no-dev --optimize-autoloader

# Chạy với PHP built-in server (phát triển)
php -S 0.0.0.0:8080

# Hoặc triển khai với PHP-FPM + Nginx / Apache cho production
```

### Lựa chọn D: Triển khai trên Laravel / Symfony

SDK tích hợp tốt với các framework PHP phổ biến:

**Laravel:**
```php
// Trong route hoặc controller
use ZaloBot\Sdk\ZaloBot;

Route::post('/webhook/zalo', function () {
    $bot = ZaloBot::fromEnv();
    $headers = getallheaders();
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$bot->webhook->verify($headers)) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $event = $bot->webhook->parseEvent($body);

    // Xử lý bất đồng bộ
    dispatch(new ProcessZaloEvent($event));

    return response()->json(['message' => 'OK']);
});
```

Thiết lập Nginx làm reverse proxy (tùy chọn).

---

## Tiếp theo

- Xem [Tham chiếu API](./api-reference.md) để biết tất cả các phương thức
- Tìm hiểu về [Sự kiện Webhook](./webhook-events.md) để xử lý tương tác người dùng
- Xem [Mã lỗi](./error-codes.md) để gỡ lỗi