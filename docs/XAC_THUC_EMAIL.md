# Xác thực email (Gmail SMTP)

Cùng kiểu **Lab 3 Plus** trên `lar_vidu1`: Laravel `MustVerifyEmail` + SMTP Gmail (mật khẩu ứng dụng 16 ký tự). Code đồ án **đã có**. File này chỉ nói cách bật Gmail trên máy bạn.

---

## 1. Làm kiểu gì (lab và đồ án)

Không tự viết SMTP. Dùng mail mặc định của Laravel.

1. `User` implement `MustVerifyEmail` → cột `users.email_verified_at`.
2. Đăng ký: tạo user (cột verify còn `null`) → login → `Registered` → Laravel gửi mail có **link ký sẵn**.
3. Khách bấm link → `email_verified_at` = lúc đó.
4. Middleware `verified` chặn **đặt hàng** và **đánh giá** nếu chưa verify.

| | Lab `lar_vidu1` | WebTheThao |
| --- | --- | --- |
| Gửi mail lúc đăng ký | `$user->sendEmailVerificationNotification()` | `event(new Registered($user))` — listener Laravel gửi hộ |
| Trang “chưa verify” | `/email/verify` | Cùng URL, view `storefront.auth.verify-email` |
| Chặn gì | Xem SP + checkout | Checkout + đánh giá (`middleware verified`) |
| Nhân viên / admin | Ít dùng | Seed sẵn `email_verified_at` — không bắt verify |

Tài khoản mẫu `khach@webthethao.test` đã verify sẵn (seed). Muốn thấy mail: **đăng ký email Gmail thật**.

---

## 2. Bật Gmail (một lần)

Gmail **không** nhận mật khẩu đăng nhập thường. Cần **mật khẩu ứng dụng**.

1. Tài khoản Google → [bảo mật](https://myaccount.google.com/security).
2. Bật **xác minh 2 bước**.
3. [Mật khẩu ứng dụng](https://myaccount.google.com/apppasswords) → tạo cho “Mail”.
4. Google cho **16 ký tự** (có thể có dấu cách — xóa khoảng trắng khi dán vào `.env`).

Không commit mật khẩu này lên git.

---

## 3. Sửa `.env` (gốc `WebTheThao`)

Mặc định `.env.example` là `MAIL_MAILER=log` (chỉ ghi `storage/logs`, không tới hộp thư). Đổi giống lab:

```env
APP_URL=http://localhost/WebTheThao/public

QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ban@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_FROM_ADDRESS=ban@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

- `MAIL_USERNAME` / `MAIL_FROM_ADDRESS` = đúng Gmail vừa tạo app password.  
- `APP_URL` phải **trùng** URL bạn mở site. Sai host thì link trong mail 403 / không verify.  
  - Apache XAMPP: `http://localhost/WebTheThao/public`  
  - `php artisan serve`: `http://127.0.0.1:8000`  
- `QUEUE_CONNECTION=sync` — gửi ngay, không cần `queue:work`.

Xong thì:

```text
php artisan config:clear
```

Không cần đổi code PHP.

---

## 4. Test tay

1. Đăng xuất. Đăng ký email Gmail **thật** (không dùng `@webthethao.test`).
2. Site nhảy `/email/verify`. Inbox (và Spam): mail từ `MAIL_FROM_ADDRESS`.
3. Bấm link (còn hạn, signed). Về trang chủ: “Email đã xác thực…”.
4. Vào `/thanh-toan` được.  
   Chưa bấm link: checkout bị đẩy lại trang verify.
5. Nút **Gửi lại email xác thực** trên trang đó nếu mail chưa tới.

phpMyAdmin: `users.email_verified_at` từ `NULL` thành datetime.

---

## 5. Lỗi thường gặp

| Hiện tượng | Cách xử |
| --- | --- |
| Không có mail | Còn `MAIL_MAILER=log` → xem `storage/logs/laravel.log`. Đổi smtp + `config:clear`. |
| `535` / Bad credentials | Sai app password, hoặc dán còn khoảng trắng, hoặc chưa bật 2FA. |
| Link verify lỗi / 403 | `APP_URL` khác URL đang mở. Sửa rồi `config:clear`. Phải **đăng nhập đúng user** mới đăng ký khi bấm link. |
| Mail vào Spam | Bình thường với Gmail SMTP cá nhân. |
| Chờ mãi | `QUEUE_CONNECTION` không phải `sync`. |
| Nhân viên không nhận mail | Đúng: tài khoản cửa hàng seed đã verify. |

---

## 6. File trong project

- `app/Domain/User/Models/User.php` — `MustVerifyEmail`
- `app/Storefront/Http/Controllers/AuthPageController.php` — đăng ký + `Registered`
- `app/Storefront/Http/Controllers/EmailVerificationController.php`
- `routes/web.php` — `/email/verify`, `/email/verify/{id}/{hash}`, gửi lại
- `resources/views/storefront/auth/verify-email.blade.php`
- Checkout / đánh giá: `middleware(['auth', 'customer', 'verified'])`

Test tự động: `php artisan test --filter=AccountUpgradeTest` (không gửi Gmail thật).
