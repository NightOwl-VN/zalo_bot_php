# Hướng dẫn Đóng góp cho Zalo Bot PHP SDK

> Ngôn ngữ: [English 🇺🇸](../../CONTRIBUTING.md) | [Tiếng Việt 🇻🇳](./contributing.md)

Cảm ơn bạn đã quan tâm đến việc đóng góp cho **Zalo Bot PHP SDK**! Chúng tôi hoan nghênh mọi đóng góp từ cộng đồng lập trình viên, từ việc báo lỗi, cải thiện tài liệu đến việc bổ sung các tính năng mới.

---

## 📜 Quy tắc Ứng xử (Code of Conduct)

Chúng tôi cam kết tạo ra một môi trường mở, hòa đồng và tôn trọng lẫn nhau. Vui lòng giao tiếp văn minh, tôn trọng ý kiến của người khác trong mọi trao đổi qua Issues, Pull Requests và thảo luận.

---

## 🛠️ Cách thức Đóng góp

### 1. Báo cáo Lỗi (Reporting Bugs)
- Tìm kiếm trong danh sách [GitHub Issues](https://github.com/NightOwl-VN/zalobot_php/issues) trước để tránh tạo trùng lặp.
- Nếu lỗi chưa được báo cáo, tạo một issue mới sử dụng mẫu **Bug Report**.
- Cung cấp tiêu đề rõ ràng, các bước tái hiện lỗi, kết quả mong đợi và kết quả thực tế, cùng thông tin môi trường (PHP version, OS, SDK version).
- Đính kèm log lỗi hoặc đoạn mã mẫu tối giản (tuyệt đối không để lộ token hay secret key thật).

### 2. Đề xuất Tính năng (Suggesting Enhancements)
- Mở issue sử dụng mẫu **Feature Request**.
- Mô tả chi tiết về tính năng đề xuất, lý do cần thiết và thiết kế API dự kiến.
- Thảo luận trước với maintainer nếu tính năng có thể làm thay đổi kiến trúc hiện tại.

### 3. Gửi Pull Request (PR)
- Mỗi PR nên tập trung giải quyết một vấn đề hoặc một tính năng cụ thể.
- Đảm bảo toàn bộ mã nguồn tuân thủ quy chuẩn viết code và vượt qua các bài kiểm tra nội bộ.

---

## 💻 Thiết lập Môi trường Phát triển Cục bộ

Các bước thiết lập môi trường để phát triển và test:

```bash
# 1. Clone repository
git clone https://github.com/NightOwl-VN/zalobot_php.git
cd zalobot-php-sdk

# 2. Cài đặt dependencies
composer install

# 3. Tạo file cấu hình biến môi trường
cp .env.example .env

# 4. Điền các thông số test vào .env (không bao giờ commit file này)
# ZALO_BOT_TOKEN=your_test_token
# ZALO_BOT_SECRET=your_test_secret
```

### Chạy thử Tests:
```bash
# Chạy toàn bộ test suite PHPUnit
composer test
# hoặc
vendor/bin/phpunit

# Chạy một file test cụ thể
vendor/bin/phpunit tests/ConfigTest.php
```

### Chạy thử Ví dụ:
```bash
# Cấu hình .env trong thư mục examples trước
cp examples/.env.example examples/.env

# Chạy ví dụ gửi tin nhắn
php examples/send-message.php
```

---

## 🎨 Quy chuẩn Viết mã nguồn (Coding & Style Guidelines)

Để duy trì mã nguồn sạch và nhất quán, vui lòng tuân thủ các quy tắc sau:

1. **PHP 8.1+ Hiện đại**:
   - Sử dụng named arguments để rõ ràng.
   - Sử dụng typed properties và `readonly` khi phù hợp.
   - Sử dụng `match` thay vì `switch` phức tạp.
   - Sử dụng enums cho các tập hợp cố định.

2. **Quy chuẩn PSR-12**:
   - Tuân thủ [PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12/).
   - 4 spaces cho việc thụt lề.
   - Một `declare(strict_types=1);` ở đầu mỗi file PHP.

3. **Tài liệu hóa bằng PHPDoc**:
   - Mọi class, method public **bắt buộc** phải có chú thích PHPDoc đầy đủ.
   - Sử dụng annotations `@param`, `@return`, và `@throws` nhất quán.

4. **Bảo mật — Không Hardcode**:
   - Tuyệt đối không commit token, secret key, IP nội bộ hay localhost vào mã nguồn.
   - Tất cả cấu hình URL phải được đọc từ `src/Config.php` hoặc file `.env`.

5. **Xử lý Lỗi (Error Handling)**:
   - Sử dụng các lớp exception tùy chỉnh từ `src/Exceptions/` (`ApiException`, `AuthException`, `ValidationException`).
   - Mọi thông báo lỗi phải được viết bằng Tiếng Anh kỹ thuật rõ ràng.

---

## 🔀 Quy chuẩn Đặt tên Commit (Git Commit Conventions)

Dự án áp dụng quy chuẩn [Conventional Commits](https://www.conventionalcommits.org/):

| Tiền tố | Mục đích sử dụng | Ví dụ |
|---|---|---|
| `feat:` | Tính năng mới | `feat: add support for interactive carousel templates` |
| `fix:` | Sửa lỗi | `fix: handle edge case in webhook signature parser` |
| `docs:` | Cập nhật tài liệu | `docs: add webhook troubleshooting guide` |
| `refactor:` | Tái cấu trúc code (không đổi tính năng) | `refactor: optimize token refresh helper` |
| `test:` | Thêm hoặc sửa tests | `test: add unit tests for message module` |
| `chore:` | Cập nhật công cụ build, dependencies | `chore: update dependencies` |

---

## 🚀 Quy trình Gửi Pull Request

1. **Tạo nhánh (Branch)**:
   Tạo nhánh mới từ nhánh `main`:
   ```bash
   git checkout -b feat/ten-tinh-nang
   # hoặc
   git checkout -b fix/mo-ta-loi
   ```

2. **Phát triển & Kiểm thử**:
   - Viết code theo đúng quy chuẩn (PSR-12).
   - Cập nhật PHPDoc tương ứng.
   - Chạy `composer test` và đảm bảo tất cả test đều pass.

3. **Commit & Push**:
   ```bash
   git add .
   git commit -m "feat: mo ta ngan gon ve thay doi"
   git push origin feat/ten-tinh-nang
   ```

4. **Mở Pull Request**:
   - Tạo PR vào nhánh `main` của repository `NightOwl-VN/zalobot_php`.
   - Mô tả ngắn gọn những gì đã thay đổi và đính kèm link issue liên quan (VD: `Closes #12`).
   - Maintainer sẽ review và phản hồi sớm nhất có thể.

---

## 📄 Giấy phép Bản quyền

Bằng việc đóng góp mã nguồn cho Zalo Bot PHP SDK, bạn đồng ý rằng các đóng góp của bạn sẽ được phát hành theo điều khoản của [Giấy phép MIT](./LICENSE).
