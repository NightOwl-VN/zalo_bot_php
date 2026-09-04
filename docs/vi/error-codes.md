# Mã lỗi

Tài liệu này liệt kê tất cả các mã lỗi có thể trả về từ Zalo Bot API và cách khắc phục.

---

## Mã lỗi API

| Mã | Mô tả | Giải pháp |
|----|-------|-----------|
| `0` | Thành công | — |
| `-1` | Lỗi hệ thống không xác định | Thử lại sau. Liên hệ Zalo nếu vẫn gặp. |
| `-2` | Tham số không hợp lệ | Kiểm tra định dạng request body và các trường bắt buộc. |
| `-3` | Access Token không hợp lệ hoặc hết hạn | Gia hạn access token trên Zalo Developer Platform. |
| `-4` | Ứng dụng không có quyền truy cập tính năng này | Kiểm tra quyền của OA. |
| `-5` | Secret Key hoặc chữ ký không hợp lệ | Xác minh secret key khớp với Zalo Developer Platform. |
| `-6` | Tài khoản Zalo Bot bị khóa hoặc vô hiệu hóa | Liên hệ Zalo support. |
| `-7` | Người dùng đã chặn bot hoặc chưa tương tác | Yêu cầu người dùng theo dõi hoặc tương tác với bot trước. |
| `-8` | File hoặc media vượt quá kích thước cho phép | Nén file. Kích thước tối đa: 10MB cho ảnh, 20MB cho file. |
| `-9` | Vượt quá giới hạn tần suất gửi tin | Giảm tốc độ request. Tối đa: 30 request/phút cho mỗi access token. |
| `-10` | Yêu cầu không được hỗ trợ hoặc endpoint không tồn tại | Kiểm tra URL API. |
| `-11` | Không tìm thấy người dùng | Xác minh user ID chính xác. |
| `-12` | Hết thời gian chờ | Tối ưu thời gian phản hồi server. Sử dụng xử lý bất đồng bộ. |
| `-13` | Lỗi phân tích định dạng nội dung gửi đi | Kiểm tra định dạng tin nhắn (text, cấu trúc JSON). |

---

## Mã trạng thái HTTP

| Trạng thái | Mô tả | Cách xử lý |
|------------|-------|------------|
| `200` | Thành công | Tiếp tục luồng bình thường. |
| `400` | Yêu cầu không hợp lệ | Kiểm tra tham số và định dạng. |
| `401` | Chưa xác thực | Gia hạn access token. |
| `403` | Từ chối truy cập | Kiểm tra quyền OA. |
| `404` | Không tìm thấy | Xác minh URL endpoint. |
| `429` | Quá nhiều yêu cầu | Đợi và thử lại. SDK tự động thử lại tối đa 3 lần. |
| `500` | Lỗi server nội bộ | Thử lại sau. Liên hệ Zalo nếu vẫn gặp. |

---

## Xử lý lỗi trong PHP

```php
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\ValidationException;

try {
    $bot->message->sendText('invalid_user', 'Xin chào');
} catch (ValidationException $e) {
    error_log('Đầu vào không hợp lệ: ' . $e->getMessage() . ' (trường: ' . ($e->getField() ?? 'không rõ') . ')');
} catch (AuthException $e) {
    error_log('Xác thực thất bại: ' . $e->getMessage());
    // Gia hạn token hoặc yêu cầu xác thực lại
} catch (RateLimitException $e) {
    error_log('Giới hạn tần suất. Thử lại sau: ' . ($e->getRetryAfter() ?? 'không rõ') . 's');
    // Đợi và thử lại
} catch (ApiException $e) {
    error_log("Lỗi API {$e->getApiErrorCode()}: {$e->getMessage()}");
    // Xử lý mã lỗi cụ thể
} catch (\Throwable $e) {
    error_log('Lỗi không xác định: ' . $e->getMessage());
}
```

---

## Các kịch bản lỗi phổ biến

### 1. Vượt quá giới hạn tần suất (`-9`)

**Nguyên nhân:** Gửi quá nhiều request trong thời gian ngắn.

**Giải pháp:**
- Sử dụng cơ chế tự động thử lại của SDK (mặc định bật, exponential backoff)
- Thêm độ trễ giữa các request
- Gửi tin nhắn theo batch khi có thể

```php
// Tăng số lần thử lại qua Config
$bot = new ZaloBot(Config::fromEnv([
    'ZALO_BOT_MAX_RETRIES' => '5',
]));
```

### 2. Access Token không hợp lệ (`-3`)

**Nguyên nhân:** Token hết hạn hoặc bị thu hồi.

**Giải pháp:**
- Tạo token mới trên Zalo Developer Platform
- Sử dụng biến môi trường để tránh hardcode
- Dùng `setBotToken()` để xoay vòng token khi chạy

```php
// Xoay vòng token khi chạy
$bot->setBotToken('your_new_token');
```

### 3. Không tìm thấy người dùng (`-11`)

**Nguyên nhân:** User ID sai hoặc người dùng chưa tương tác với bot.

**Giải pháp:**
- Xác minh user ID từ sự kiện webhook
- Đảm bảo người dùng đã theo dõi OA
- Sử dụng user ID hợp lệ từ Zalo Developer Platform

### 4. Chữ ký Webhook không hợp lệ (`403`)

**Nguyên nhân:** Secret key không khớp hoặc body bị sửa đổi.

**Giải pháp:**
- Xác minh secret key trong `.env` khớp với Zalo Developer Platform
- Đảm bảo truyền raw request body (không phải bản re-serialize)
- Kiểm tra việc đọc header `X-Bot-Api-Secret-Token` đúng cách

### 5. Lỗi Validation

**Nguyên nhân:** Đầu vào không hợp lệ truyền vào phương thức SDK.

**Giải pháp:**
```php
use ZaloBot\Sdk\Exceptions\ValidationException;

try {
    $bot->message->sendText('', 'Xin chào');
} catch (ValidationException $e) {
    error_log("Trường '{$e->getField()}' lỗi: {$e->getMessage()}");
}
```
