# Sự kiện Webhook

Tài liệu này bao gồm cách thiết lập webhook, xác minh secret token và xử lý sự kiện đến với PHP SDK.

---

## Thiết lập Webhook

1. Truy cập [Zalo Bot Platform](https://bot.zapps.me/)
2. Chọn bot của bạn
3. Vào mục **Webhook**
4. Nhập URL webhook của bạn (bắt buộc HTTPS)
5. Cấu hình cùng secret token trong biến môi trường `ZALO_BOT_SECRET` của SDK

> ⚠️ Webhook của bạn phải phản hồi `200 OK` trong vòng **5 giây**. Zalo có thể thử lại request không được xác nhận.

---

## Xác minh Secret Token

Zalo gửi secret token trong header `X-Bot-Api-Secret-Token`. SDK so sánh bằng hàm `hash_equals()` an toàn về mặt thời gian của PHP.

### Endpoint PHP thuần

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
    // Xử lý sự kiện. Đưa công việc nặng vào hàng đợi nếu có thể.
    http_response_code(200);
    echo json_encode(['message' => 'OK']);
} catch (\ZaloBot\Sdk\Exceptions\WebhookException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['message' => $e->getMessage()]);
}
```

### Handler tiện lợi

`handle()` xác minh header, phân tích body và gọi handler của bạn:

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

---

## Định dạng Payload Sự kiện

`parseEvent()` chấp nhận cả hai định dạng wrapped và flat.

### Wrapped (chuẩn từ Zalo)

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
      "text": "Chào bot!"
    }
  }
}
```

### Đối tượng sự kiện chuẩn hóa

```php
[
    'event' => 'user_text',
    'eventName' => 'message.text.received',
    'userId' => '123456789',
    'chatId' => '123456789',
    'messageId' => 'msg_xyz',
    'timestamp' => 1623456789,
    'message' => ['text' => 'Chào bot!'],
    'raw' => [/* payload gốc */],
]
```

---

## Các Loại Sự kiện

| Sự kiện chuẩn hóa | Sự kiện Zalo gốc | Message chuẩn hóa |
|---|---|---|
| `user_text` | `message.text.received` | `['text' => string]` |
| `user_image` | `message.image.received` | `['photo' => array, 'caption' => ?string]` |
| `user_sticker` | `message.sticker.received` | `['sticker' => array]` |
| `user_voice` | `message.voice.received` | `['voiceUrl' => ?string]` |
| `user_unsupported` | `message.unsupported.received` | Mảng message gốc |
| `user_follow` | `user.follow` | Mảng message gốc |
| `user_unfollow` | `user.unfollow` | Mảng message gốc |

### Xử lý sự kiện

```php
switch ($event['event']) {
    case 'user_text':
        $bot->message->sendText($event['chatId'], 'Bạn đã nói: ' . $event['message']['text']);
        break;
    case 'user_follow':
        $bot->message->sendText($event['chatId'], 'Cảm ơn bạn đã theo dõi! 🎉');
        break;
    case 'user_unfollow':
        error_log('Người dùng đã hủy theo dõi: ' . $event['userId']);
        break;
    default:
        error_log('Sự kiện chưa xử lý: ' . $event['event']);
}
```

---

## Thực hành tốt nhất

1. **Luôn xác minh secret token** trước khi phân tích hoặc xử lý request.
2. **Phản hồi trong 5 giây**; đưa công việc tốn kém vào hàng đợi.
3. **Xử lý tất cả các loại sự kiện** và ghi log sự kiện lạ để tương thích về sau.
4. **Handler cần idempotent** vì có thể nhận trùng lặp.
5. **Dùng tên sự kiện chuẩn hóa `event`**, giữ `eventName` để chẩn đoán.
6. **Ghi log event, userId, messageId** nhưng không ghi log secret.
7. **Bắt exception trong handler** để lỗi tạm thời không kích hoạt retry không mong muốn.

---

## Vấn đề thường gặp

| Vấn đề | Giải pháp |
|--------|-----------|
| `403 Invalid webhook secret token` | Kiểm tra `ZALO_BOT_SECRET` và chính tả header |
| Thiếu `event_name` | Truyền object JSON đã decode, không phải chuỗi JSON gốc |
| URL webhook không truy cập được | Dùng endpoint HTTPS công khai, kiểm tra firewall/DNS |
| Timeout | Trả 200 ngay và xử lý bất đồng bộ |
| Sự kiện trùng lặp | Xử lý idempotent dựa trên `messageId` |
