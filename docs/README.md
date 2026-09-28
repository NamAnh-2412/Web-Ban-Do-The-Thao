# Tài liệu đồ án WebTheThao

Mọi file markdown **của đồ án** nằm trong folder này (`docs/`). Không đọc các `.md` trong `vendor/` — đó là tài liệu thư viện Laravel, không phải bài của bạn.

Bắt đầu đọc theo thứ tự: [YEU_CAU_VA_KE_HOACH.md](YEU_CAU_VA_KE_HOACH.md) → [ERD_VA_LUONG.md](ERD_VA_LUONG.md) → test tay theo script trong file ERD. Nâng cấp (việc quan trọng lên đầu): [NANG_CAP.md](NANG_CAP.md).

---

## File markdown trong `docs/`

| File | Chức năng |
| --- | --- |
| [README.md](README.md) | Mục lục tài liệu + giải thích folder/file dự án (file này). |
| [YEU_CAU_VA_KE_HOACH.md](YEU_CAU_VA_KE_HOACH.md) | Đề bài: một môn TMĐT, kiến trúc Laravel, tài khoản, chức năng đã có, không làm SOA. |
| [ERD_VA_LUONG.md](ERD_VA_LUONG.md) | ERD một schema, luồng bán / thuê / quán chốt, script demo 10–15 phút. |
| [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md) | MoMo sandbox đã gắn; QR tĩnh không tự chốt. |
| [NANG_CAP.md](NANG_CAP.md) | Việc nâng cấp theo ưu tiên + nhật ký MoMo/GHN 2026-09-25. |
| [DOI_CHIEU_LAB.md](DOI_CHIEU_LAB.md) | Audit `lar_vidu1` vs đồ án: module, API, khoảng trống. |
| [XAC_THUC_EMAIL.md](XAC_THUC_EMAIL.md) | Bật Gmail SMTP (Lab 3 Plus) để nhận mail xác thực. |
| [HUONG_PHAT_TRIEN.md](HUONG_PHAT_TRIEN.md) | Đối chiếu lab `lar_vidu1` + PDF thầy; chi tiết kỹ thuật từng pha. |

`README.md` ở **gốc dự án** chỉ trỏ vào folder này.

---

## Folder gốc dự án — chứa gì, để làm gì

| Folder / file | Chức năng | Bên trong |
| --- | --- | --- |
| **docs/** | Tài liệu nộp / bảo vệ | Các file `.md` ở bảng trên. |
| **app/** | Code nghiệp vụ PHP | `Domain` (quy tắc bán/thuê), `Gateway` (ghép checkout, thanh toán, GHN, MoMo, email), controller Blade khách và admin. |
| **routes/** | Đường dẫn URL | `web.php` = toàn bộ trang khách + `/admin`. `console.php` lịch nhắc thuê và nhả đơn hết hạn. |
| **resources/views/** | Giao diện Blade | `storefront/` trang khách; `admin/` khu cửa hàng (kể cả POS, tài chính, tin nhắn); `layouts/` khung admin và quầy. |
| **public/css** / **public/js** | CSS và JS tĩnh | `public/css/storefront/`, `admin/`, `shared/` và `public/js/` tương ứng, nạp theo từng trang. |
| **public/** | Cửa vào website (Apache) | `index.php`, ảnh public, `storage` (symlink). URL: `http://localhost/WebTheThao/public`. |
| **database/** | CSDL | `migrations/` tạo bảng; `seeders/` dữ liệu demo; `factories/` cho test. |
| **config/** | Cấu hình Laravel | `database.php`, `auth.php`, `payments.php`, `notifications.php`. |
| **bootstrap/** | Khởi động app | `app.php` đăng ký middleware `auth` / `admin` / `customer` / `owner`. Tin `TRUSTED_PROXIES` khi biến này có giá trị (Render). |
| **docker/** + **Dockerfile** | Image chạy trên Render | Nginx, PHP-FPM, entrypoint migrate/seed, mẫu biến `docker/render.env.example`. Máy XAMPP không cần cài Docker. |
| **tests/** | Test tự động PHPUnit | Auth, admin CRUD, POS tại quầy, checkout, coupon, thông báo. Chạy: `php artisan test`. |
| **storage/** | File sinh ra khi chạy | Log, session, cache view, ảnh upload `app/public/products`. Không sửa tay. |
| **vendor/** | Thư viện Composer | Laravel và package. Không nộp như code của mình, không sửa. |
| **.env** | Mật khẩu / DB máy bạn | `webthethao`, `root`, mật khẩu trống. Không commit nếu đưa lên git công khai. |

---

## Folder `app/` — code chính

| Folder | Chức năng |
| --- | --- |
| **app/Domain/Chat** | Hội thoại 1 thread cửa hàng ↔ khách. |
| **app/Domain/User** | User, role khách / nhân viên / quản trị. |
| **app/Domain/Product** | Danh mục, môn, sản phẩm, variant, `offer_mode`. |
| **app/Domain/Inventory** | Tồn bán, món thuê, khóa / trừ kho. |
| **app/Domain/Order** | Đơn, dòng mua/thuê, trạng thái. |
| **app/Domain/Payment** | Khoản tiền hàng / cọc / hoàn. Màn tài chính admin đọc các khoản này. |
| **app/Domain/Rental** | Lịch thuê, gia hạn, trả, sự cố. |
| **app/Domain/Shipping** | Enum hình thức nhận và trạng thái giao. Gọi GHN nằm ở Gateway. |
| **app/Domain/Coupon** | Mã giảm (không trừ cọc). |
| **app/Domain/Review** | Đánh giá sau khi đơn đã trả. |
| **app/Domain/Notification** | Hàng đợi email thông báo. |
| **app/Domain/Report** | Số liệu báo cáo admin. |
| **app/Storefront** | Controller và giỏ hàng trang khách. |
| **app/Http/Controllers/Admin** | Form quản trị: sản phẩm, kho, đơn, thuê, POS, tài chính, tin nhắn, user, coupon, báo cáo. |
| **app/Http/Support** | Giỏ quầy (`PosCartService`), chỉ POS gọi. |
| **app/Http/Middleware** | `EnsureCustomer`, `EnsureAdmin`, `EnsureOwnerAdmin`. |
| **app/Gateway** | Orchestrator nội bộ: `CheckoutOrchestrator`, `PaymentOrchestrator`, `NotificationOrchestrator`, `ShippingService`, `MomoCheckoutService`, client GHN và MoMo. |

Trang web đi qua controller Blade. `CatalogProductIndexRequest` chỉ lọc form catalog. Không có tầng REST public.

---

## Folder `resources/views/`

| Folder | Chức năng |
| --- | --- |
| **storefront/** | Home, catalog, chi tiết sản phẩm, giỏ, checkout, đơn khách, tin nhắn, đăng nhập/ký, chính sách, thông báo. |
| **admin/** | Dashboard, POS, tài chính, danh mục, môn, sản phẩm, kho, đơn, thanh toán, lịch thuê, tin nhắn, user, coupon, review, báo cáo. |
| **layouts/** | Khung sidebar quản trị và khung bán tại quầy. |

---

## File gốc hay dùng khi test

| File | Chức năng |
| --- | --- |
| `routes/web.php` | Tất cả URL. |
| `database/migrations/2026_08_22_100000_create_webthethao_schema.php` | Toàn bộ bảng MySQL. |
| `database/seeders/DatabaseSeeder.php` | Tài khoản mẫu + gọi seed catalog/kho. |
| `.env` | Kết nối DB, `APP_URL`. |
