# Đề tài: Thiết Kế Website Thương Mại Điện Tử Bán và Cho Thuê Đồ Thể Thao (WebTheThao)

Hệ thống thương mại điện tử cho một cửa hàng đồ thể thao: vừa bán vừa cho thuê trên cùng catalog, có giỏ hàng, thanh toán, giao hàng, chat với cửa hàng và quầy bán tại chỗ.

---

## 1. Công nghệ & Kiến trúc Hệ thống

- **Framework**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade Templates, Bootstrap 5, CSS/JS tĩnh trong `public/`
- **Database**: MySQL (`webthethao`)
- **Kiến trúc**: Controller Blade gọi Domain (quy tắc nghiệp vụ) và Gateway (checkout, MoMo, GHN). Một database, không tách microservice
- **Xử lý giao dịch**: Database Transactions khi đặt hàng, khóa kho và ghi khoản tiền
- **Thanh toán**: MoMo sandbox, chuyển khoản QR tĩnh, tiền mặt tại quầy, COD khi giao nhà. Tiền hàng và tiền cọc là hai khoản riêng
- **Vận chuyển**: API Giao Hàng Nhanh (GHN) lấy tỉnh, quận, phường và phí
- **Hỗ trợ khách hàng**: Một hội thoại giữa mỗi khách và cửa hàng
- **Thông báo**: Hàng đợi email (xác thực, đơn, nhắc thuê)
- **Phân quyền**: `customer`, `staff`, `admin` với middleware `customer`, `admin`, `owner`

---

## 2. Cấu trúc Thư mục Dự án (Project Structure)

```
WebTheThao/
├── app/
│   ├── Domain/                                 # Quy tắc nghiệp vụ, không biết URL
│   │   ├── User/                               # Tài khoản, vai trò khách / nhân viên / quản trị
│   │   ├── Product/                            # Môn, danh mục, sản phẩm, biến thể, bán / thuê / cả hai
│   │   ├── Inventory/                          # Tồn bán theo số lượng, từng món thuê, giữ chỗ
│   │   ├── Order/                              # Đơn web hoặc quầy, dòng mua / thuê, trạng thái
│   │   ├── Rental/                             # Lịch thuê, giá ngày-tuần, gia hạn, trả, sự cố
│   │   ├── Payment/                            # Khoản tiền hàng, cọc, hoàn, QR, phiên MoMo
│   │   ├── Shipping/                           # Nhận tại shop hoặc giao nhà, trạng thái giao
│   │   ├── Coupon/                             # Mã giảm, không trừ tiền cọc
│   │   ├── Review/                             # Đánh giá sau khi đơn đã xong
│   │   ├── Chat/                               # Một thread cửa hàng ↔ một khách
│   │   ├── Notification/                       # Hàng đợi email
│   │   └── Report/                             # Số liệu báo cáo quản trị
│   ├── Gateway/                                # Ghép checkout và gọi dịch vụ ngoài
│   │   ├── Services/CheckoutOrchestrator.php   # Tạo đơn, khóa kho, chốt, hủy
│   │   ├── Services/PaymentOrchestrator.php    # Thu tiền, xác nhận CK, hoàn
│   │   ├── Services/MomoCheckoutService.php    # Mở MoMo, xử lý khách quay về và IPN
│   │   ├── Services/ShippingService.php        # Phí ship và vận đơn
│   │   ├── Services/NotificationOrchestrator.php # Chọn email gửi sau mỗi bước
│   │   ├── Momo/MomoClient.php                 # Ký request và gọi API MoMo
│   │   ├── Shipping/GhnClient.php              # Gọi API GHN
│   │   └── Media/CloudinaryImageStore.php      # Upload và xóa ảnh sản phẩm
│   ├── Storefront/Http/Controllers/            # Trang khách
│   │   ├── CatalogPageController.php           # Trang chủ, danh sách, chi tiết
│   │   ├── CartPageController.php              # Xem, thêm, xóa giỏ
│   │   ├── CheckoutPageController.php          # Thanh toán, đơn của tôi, gia hạn, đánh giá
│   │   ├── AuthPageController.php              # Đăng nhập, đăng ký, đăng xuất
│   │   ├── PasswordResetController.php         # Quên mật khẩu và đặt lại
│   │   ├── EmailVerificationController.php     # Xác thực email
│   │   ├── ProfilePageController.php           # Hồ sơ
│   │   ├── MessagePageController.php           # Chat với cửa hàng (kèm thẻ SP/đơn)
│   │   ├── MyOrdersController.php              # Đơn của tôi (tab Mua / Thuê)
│   │   ├── MomoCallbackController.php          # MoMo return và IPN
│   │   └── ShippingLookupController.php        # JSON tỉnh, quận, phường, phí
│   ├── Http/
│   │   ├── Controllers/Admin/                  # Nhân viên và quản trị
│   │   │   ├── DashboardController.php         # Tổng quan
│   │   │   ├── CategoryController.php          # Danh mục
│   │   │   ├── SportController.php             # Môn thể thao
│   │   │   ├── ProductController.php           # Sản phẩm, biến thể, ảnh
│   │   │   ├── InventoryController.php         # Tồn bán và món thuê
│   │   │   ├── PosController.php               # Bán tại quầy
│   │   │   ├── OrderController.php             # Chốt, thu, hủy, giao hàng
│   │   │   ├── PaymentController.php           # Xác nhận khoản, QR, hoàn tiền
│   │   │   ├── FinanceController.php           # Đối soát và xuất
│   │   │   ├── RentalController.php            # Giao đồ, trả, gia hạn, hoàn cọc
│   │   │   ├── ReviewController.php            # Xem và xóa đánh giá
│   │   │   ├── MessageController.php           # Hộp thư cửa hàng
│   │   │   ├── HomeBannerController.php        # Ảnh banner trang chủ
│   │   │   ├── UserController.php              # Tạo và khóa tài khoản cửa hàng
│   │   │   ├── CouponController.php            # Mã giảm
│   │   │   └── ReportController.php            # Báo cáo, chỉ quản trị
│   │   ├── Middleware/                         # customer, admin, owner
│   │   └── Support/PosCartService.php          # Giỏ quầy, tách khỏi giỏ website
│   ├── Console/Commands/                       # Nhắc thuê 8:00, nhả đơn hết hạn mỗi 15 phút
│   └── Providers/AppServiceProvider.php        # Gắn giỏ POS, đếm tin chưa đọc
├── bootstrap/                                  # Khởi động app và đăng ký middleware
├── config/                                     # database, auth, mail, payments, services
├── database/
│   ├── migrations/                             # Tạo và sửa bảng MySQL
│   ├── seeders/                                # Tài khoản mẫu, mã SALE10, catalog, kho
│   └── factories/                              # Dữ liệu giả cho PHPUnit
├── public/                                     # index.php, CSS, JS, ảnh demo
├── resources/views/
│   ├── storefront/                             # Trang khách: home, catalog, giỏ, checkout, đơn, chat
│   ├── admin/                                  # Bảng quản trị, gồm POS, tài chính, thuê
│   ├── layouts/                                # Khung sidebar admin và khung quầy
│   └── partials/                               # QR, CSS/JS, form bồi thường
├── routes/
│   ├── web.php                                 # Mọi URL khách và /admin
│   └── console.php                             # Lịch nhắc thuê và nhả giữ chỗ
├── storage/                                    # Ảnh upload, log, session, cache view
├── tests/                                      # Feature và Unit: auth, checkout, POS, thuê, MoMo
└── docker/                                     # Image Nginx + PHP để chạy trên Render
```

---

## 3. Cấu trúc Cơ sở Dữ liệu Chính

1. **`users`**: Khách, nhân viên, quản trị. Khách phải xác thực email.
2. **`sports`** & **`categories`**: Môn thể thao và danh mục.
3. **`products`** & **`product_variants`**: Sản phẩm và biến thể. `offer_mode` là chỉ bán, chỉ thuê, hoặc cả hai. Biến thể có giá bán, giá thuê theo ngày và tiền cọc.
4. **`inventory_stocks`** & **`inventory_items`**: Tồn bán theo số lượng, và từng món cho thuê.
5. **`inventory_stock_reservations`** & **`inventory_item_reservations`**: Giữ chỗ khi đơn còn chờ.
6. **`orders`** & **`order_items`**: Đơn kênh web hoặc quầy, địa chỉ, phí GHN, mã giảm, dòng mua hoặc thuê.
7. **`rental_bookings`**, **`rental_extensions`**, **`rental_returns`**, **`rental_incidents`**: Lịch thuê, gia hạn, phiếu trả, sự cố.
8. **`payments`** & **`gateway_sessions`**: Khoản tiền hàng, cọc, hoàn, và phiên MoMo.
9. **`payment_qr_settings`**: Tài khoản và QR chuyển khoản của shop.
10. **`coupons`** & **`coupon_redemptions`**: Mã giảm và lần một đơn đã dùng mã.
11. **`reviews`**: Đánh giá của khách cho một dòng đơn.
12. **`conversations`** & **`messages`**: Chat một thread.
13. **`notifications`**: Hàng đợi email.

---

## 4. Các tính năng nổi bật

### Phân hệ Khách hàng (Storefront)

- **Catalog**: Lọc theo môn, danh mục, bán hoặc thuê. Một sản phẩm có thể vừa bán vừa thuê.
- **Giỏ hỗn hợp**: Dòng mua và dòng thuê trong cùng giỏ. Thuê chọn theo ngày, hệ thống chặn trùng món cùng kỳ.
- **Thanh toán**: Nhận tại shop hoặc giao nhà. MoMo, chuyển khoản QR, hoặc COD. Mã giảm chỉ trừ tiền hàng và tiền thuê.
- **Đơn và thuê**: Xem đơn, xin gia hạn, xem lịch thuê, đánh giá khi đơn đã xong.
- **Chat**: Khách đã đăng nhập nhắn một hội thoại với cửa hàng.
- **GHN**: Chọn tỉnh, quận, phường rồi tính phí giao.

### Phân hệ Quản trị (`/admin`)

- **Nhân viên**: Danh mục, môn, sản phẩm, kho, bán tại quầy, chốt đơn online, thanh toán, tài chính, lịch thuê, tin nhắn, đánh giá.
- **Quản trị**: Thêm nhân viên, mã giảm, báo cáo.
- **Bán tại quầy**: Ba cột (nhóm, thẻ sản phẩm, giỏ), nhiều phiếu. Thanh toán thì đơn thành đã thu. Lưu đơn thì giữ chờ chốt.
- **Thuê**: Xác nhận, giao đồ, ghi trả và bồi thường, duyệt gia hạn, hoàn cọc.
- **Đơn online**: Khách checkout thành chờ chốt và kho bị khóa. Quán chốt, thu đủ rồi mới sang đã thanh toán.

---

## 5. Hướng dẫn Cài đặt & Vận hành

### Yêu cầu môi trường

- PHP >= 8.2
- Composer >= 2
- XAMPP: Apache + MySQL `127.0.0.1:3306`

### Các bước cài đặt

```bash
# 1. Cài thư viện PHP
composer install

# 2. Cấu hình môi trường
copy .env.example .env
php artisan key:generate

# 3. Tạo database webthethao trong phpMyAdmin, rồi chạy
php artisan migrate --seed
php artisan storage:link
```

Mở site tại: `http://localhost/WebTheThao/public`

---

## 6. Tài khoản Thử nghiệm Mẫu

Mật khẩu cả ba tài khoản là `password`.

- **Quản trị**: `admin@webthethao.test`
- **Nhân viên**: `nhanvien@webthethao.test`
- **Khách**: `khach@webthethao.test`

## 7. Đăng nhập và đăng ký

Khách tự đăng ký trên website. Nhân viên và quản trị không có form đăng ký. Quản trị tạo họ trong `/admin/users`.

**Đăng ký khách**

1. Mở `/dang-ky`, điền tên, email, mật khẩu.
2. Hệ thống tạo user vai trò `customer`, cột `email_verified_at` còn trống, rồi đăng nhập và chuyển tới `/email/verify`.
3. Laravel gửi email có link đã ký. Khách bấm link thì `email_verified_at` được ghi giờ xác thực.
4. Chưa bấm link vẫn xem catalog và giỏ. Checkout (`/thanh-toan`) và gửi đánh giá bị chặn cho đến khi xác thực xong.
5. Trang `/email/verify` có nút gửi lại email.

Ba tài khoản seed (`admin@`, `nhanvien@`, `khach@webthethao.test`) đã xác thực sẵn, mật khẩu `password`. Muốn thấy mail thật thì đăng ký bằng Gmail thật, không dùng `@webthethao.test`.

**Bật Gmail để nhận mail xác thực**

Mặc định `.env` dùng `MAIL_MAILER=log`: mail chỉ ghi vào `storage/logs/laravel.log`, không tới hộp thư. Gmail không nhận mật khẩu đăng nhập thường. Cần mật khẩu ứng dụng 16 ký tự.

1. Vào bảo mật tài khoản Google, bật xác minh 2 bước.
2. Tạo mật khẩu ứng dụng cho Mail. Xóa khoảng trắng khi dán.
3. Sửa `.env` ở gốc dự án:

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

4. `APP_URL` phải trùng URL đang mở site. Apache XAMPP dùng dòng trên. `php artisan serve` thì đổi thành `http://127.0.0.1:8000`. Sai host thì link trong mail không xác thực được.
5. `QUEUE_CONNECTION=sync` để gửi ngay, không cần `queue:work`.
6. Chạy `php artisan config:clear`.

Không commit mật khẩu ứng dụng lên git.

**Đăng nhập**

- Khách: `/dang-nhap`. Session Laravel, không dùng JWT.
- Quên mật khẩu: `/quen-mat-khau`, rồi mở link trong mail. Tối đa 6 lần mỗi phút.
- Nhân viên và quản trị cũng đăng nhập ở `/dang-nhap`, sau đó vào `/admin`. Khách không vào `/admin`. Tài khoản cửa hàng không mua trên website.

## 8. Bật và tắt website

Trang trên Render: [https://web-ban-do-the-thao-3xpb.onrender.com](https://web-ban-do-the-thao-3xpb.onrender.com). Service tên **Web-Ban-Do-The-Thao**.

**Chế độ tự ngủ (gói free)**

Không cần bấm gì. Một lúc không ai vào, Render tự tắt. Lần mở sau đợi khoảng một phút thì trang hiện lại.

**Chế độ tắt hẳn và bật lại**

Tắt:

1. Vào [render.com](https://render.com), mở service **Web-Ban-Do-The-Thao**.
2. Vào **Settings**.
3. Bấm **Suspend Web Service**.
4. Gõ đúng dòng sau vào ô trên trang Render, rồi bấm nút đỏ. Không chạy dòng này trong PowerShell.

```text
sudo suspend web service Web-Ban-Do-The-Thao
```

Sau khi tắt, mở URL sẽ không vào được cho đến khi bật lại.

Bật lại:

1. Mở cùng service **Web-Ban-Do-The-Thao**.
2. Vào **Settings**.
3. Bấm **Resume Web Service**.
4. Đợi trạng thái **Live**, rồi mở URL ở trên.

---

## 9. Từng thư mục và từng file

Một request không đi thẳng từ Blade xuống MySQL. Controller chỉ nhận form. Quy tắc nằm trong `app/Domain`. Việc ghép nhiều domain và gọi MoMo, GHN nằm trong `app/Gateway`.

### Gốc dự án

| File | Việc nó làm |
| --- | --- |
| `artisan` | Cổng lệnh Laravel: `migrate`, `db:seed`, `test`, hai lệnh nhắc thuê và nhả giữ chỗ |
| `composer.json` | Khai báo PHP 8.2 và Laravel 12 |
| `composer.lock` | Khóa đúng phiên bản thư viện đã cài |
| `phpunit.xml` | Cấu hình chạy test |
| `.env` / `.env.example` | DB, mail, MoMo, GHN, Cloudinary. `.env` không đưa lên git |
| `.gitignore` | Loại `.env`, `vendor`, file sinh ra khi chạy |
| `.editorconfig` | Thống nhất thụt dòng |
| `.dockerignore` | File không đưa vào image |
| `Dockerfile` | Đóng gói Nginx + PHP để chạy trên Render |
| `README.md` | File này |

`vendor/` là Laravel và package Composer. Không sửa, không tính là code đồ án.

### `bootstrap/`

| File | Việc nó làm |
| --- | --- |
| `app.php` | Nạp `routes/web.php` và `routes/console.php`. Đặt alias `admin`, `customer`, `owner`. Tin proxy khi có `TRUSTED_PROXIES`. Bỏ CSRF cho IPN MoMo |
| `providers.php` | Đăng ký `AppServiceProvider` |
| `cache/` | File Laravel tối ưu khi chạy |

### `config/`

| File | Việc nó làm |
| --- | --- |
| `app.php` | Tên app, timezone, locale |
| `database.php` | Kết nối MySQL `webthethao` |
| `auth.php` | Đăng nhập session, model `User` |
| `session.php` | Phiên đăng nhập và giỏ hàng |
| `cache.php` | Nơi lưu cache |
| `queue.php` | Hàng đợi job gửi mail |
| `logging.php` | File log |
| `filesystems.php` | Đĩa lưu ảnh |
| `mail.php` | SMTP |
| `payments.php` | Khóa MoMo và tài khoản QR |
| `services.php` | Token GHN và Cloudinary |
| `notifications.php` | Bật hoặc tắt từng loại email |
| `seeding.php` | Có seed dữ liệu demo lúc deploy hay không |

### `routes/`

| File | Việc nó làm |
| --- | --- |
| `web.php` | Mọi URL khách và `/admin`. Nhóm `guest`, `auth`, `customer`, `verified`, `admin`, `owner` |
| `console.php` | 8:00 chạy `webthethao:notify-rentals`. Mỗi 15 phút chạy `webthethao:release-expired`. Múi giờ `Asia/Ho_Chi_Minh` |

### `app/Domain/User`

| File | Việc nó làm |
| --- | --- |
| `Enums/UserRole.php` | `customer`, `staff`, `admin` |
| `Models/User.php` | Tài khoản đăng nhập, bắt buộc xác thực email. `isCustomer`, `isStoreAccount`, `isAdmin`, `isOwnerAdmin`. Một user có nhiều đơn và một hội thoại |
| `Notifications/ResetPasswordNotification.php` | Nội dung mail đặt lại mật khẩu |
| `Services/UserDirectory.php` | Tìm nhiều user theo id cho màn admin |
| `app/Models/User.php` | Lớp mỏng trỏ về model Domain để Laravel nhận đúng model đăng nhập |

### `app/Domain/Product`

| File | Việc nó làm |
| --- | --- |
| `Models/Sport.php` | Môn thể thao |
| `Models/Category.php` | Danh mục |
| `Models/Product.php` | Sản phẩm: slug, ảnh, môn, danh mục. `imageSrc()` trả URL local hoặc Cloudinary. `isSaleSoldOut()` xem hàng bán đã hết chưa |
| `Models/ProductVariant.php` | Size hoặc phiên bản: giá bán, giá thuê/ngày, giá tuần, tiền cọc |
| `Enums/OfferMode.php` | `sale`, `rental`, `both` |
| `Enums/VariantCondition.php` | Tình trạng biến thể |
| `Services/CatalogService.php` | Danh sách môn và danh mục, lọc sản phẩm, tìm theo slug, biến thể lúc checkout, sản phẩm bán chạy |
| `Services/ProductWriter.php` | Tạo, sửa, xóa sản phẩm và biến thể |
| `Support/CatalogCache.php` | Cache danh sách catalog |
| `Http/Requests/CatalogProductIndexRequest.php` | Kiểm tra query string form lọc catalog |

### `app/Domain/Inventory`

Hàng bán đếm theo số lượng. Hàng thuê là từng món, vì một cây vợt không cho hai người thuê cùng ngày.

| File | Việc nó làm |
| --- | --- |
| `Models/InventoryStock.php` | Tồn bán của một biến thể |
| `Models/InventoryItem.php` | Một món thuê, có mã và trạng thái |
| `Models/InventoryStockReservation.php` | Khóa một lượng hàng bán khi đơn còn chờ |
| `Models/InventoryItemReservation.php` | Khóa một món thuê trong khoảng ngày |
| `Enums/ItemStatus.php` | `available` → `rented` hoặc `maintenance`. `rented` → `inspecting`. `inspecting` → `available` hoặc `maintenance`. `maintenance` → `available` |
| `Enums/ReservationStatus.php` | Đang giữ, đã trừ kho, đã nhả |
| `Services/AvailabilityService.php` | Còn bán bao nhiêu. Món nào còn trống trong khoảng ngày |
| `Services/InventoryWriter.php` | Cộng tồn, tạo món, đổi trạng thái, giữ chỗ, trừ kho khi đã thu, nhả khi hủy, trả món về kho |

### `app/Domain/Order`

| File | Việc nó làm |
| --- | --- |
| `Models/Order.php` | Đơn: khách, kênh web hoặc quầy, địa chỉ, phí ship, giảm giá, trạng thái |
| `Models/OrderItem.php` | Một dòng mua hoặc thuê. Có thể có một đánh giá |
| `Enums/LineType.php` | Dòng bán hoặc dòng thuê |
| `Enums/OrderChannel.php` | `web` hoặc `pos` |
| `Enums/OrderStatus.php` | Máy trạng thái đơn, xem mục 11 |
| `Services/OrderWriter.php` | Tạo đơn và các dòng. Chỉ chuyển trạng thái nếu `allowedNext()` cho phép. Cộng tiền thuê khi gia hạn. Tự hoàn tất đơn khi mọi lịch thuê đã trả |

### `app/Domain/Rental`

| File | Việc nó làm |
| --- | --- |
| `Models/RentalBooking.php` | Một lịch thuê gắn đơn, khách, món, biến thể. Có gia hạn, một phiếu trả, các sự cố |
| `Models/RentalExtension.php` | Xin gia hạn đến ngày mới |
| `Models/RentalReturn.php` | Tình trạng lúc trả và tiền bồi thường |
| `Models/RentalIncident.php` | Mất hoặc hỏng |
| `Enums/BookingStatus.php` | Máy trạng thái lịch thuê, xem mục 11 |
| `Enums/ExtensionStatus.php` | `pending`, `approved`, `rejected` |
| `Enums/ReturnCondition.php` | Đồ trả còn tốt, trầy, hoặc hỏng |
| `Enums/IncidentType.php` | Loại sự cố |
| `Services/RentalPricer.php` | Tiền thuê theo số ngày. Đủ tuần thì dùng giá tuần nếu có |
| `Services/RentalSession.php` | Nhớ ngày bắt đầu và kết thúc khách đang chọn trên giỏ |
| `Services/RentalWriter.php` | Tạo lịch, xác nhận, giao đồ, đánh dấu quá hạn, xin và duyệt hoặc từ chối gia hạn, ghi phiếu trả, tính phần cọc được hoàn, đồng bộ lịch khi đơn bị chốt, thu tiền, hoặc hủy |

### `app/Domain/Payment`

Tiền hàng và tiền cọc là hai khoản. Mã giảm không trừ cọc.

| File | Việc nó làm |
| --- | --- |
| `Models/Payment.php` | Một khoản gắn đơn |
| `Models/PaymentQrSetting.php` | Ngân hàng, số tài khoản, ảnh QR tĩnh |
| `Models/GatewaySession.php` | Một lần mở MoMo: số tiền, mã giao dịch, payload gửi và nhận |
| `Enums/PaymentKind.php` | `merchandise` tiền hàng, `deposit` cọc, `refund` hoàn |
| `Enums/PaymentMethod.php` | `cash`, `bank_transfer`, `momo`, `cod`. Enum còn giá trị `vnpay` |
| `Enums/PaymentStatus.php` | `pending`, `completed`, `failed`, `cancelled` |
| `Enums/GatewaySessionStatus.php` | Phiên MoMo đang chờ hoặc đã xong |
| `Services/PaymentWriter.php` | Tạo khoản lúc checkout, đánh dấu đã thu hoặc thất bại, hủy khoản chờ, ghi tiền thu thêm, hoàn trong phần còn hoàn được |
| `Services/FinanceService.php` | Lọc đơn cho màn đối soát. Cập nhật trạng thái đơn COD |
| `Support/BankTransferQr.php` | Dựng chuỗi VietQR từ số tiền và nội dung chuyển khoản |

### `app/Domain` còn lại

| File | Việc nó làm |
| --- | --- |
| `Shipping/Enums/ShippingMethod.php` | Nhận tại shop hoặc giao nhà |
| `Shipping/Enums/FulfillmentStatus.php` | `pickup`, `pending`, `ready_to_pick`, `delivering`, `delivered`, `cancelled`, `failed` |
| `Coupon/Models/Coupon.php` | Mã: loại giảm, giá trị, đơn tối thiểu, số lần dùng, còn hiệu lực |
| `Coupon/Models/CouponRedemption.php` | Một đơn đã dùng mã |
| `Coupon/Enums/DiscountType.php` | Phần trăm hoặc số tiền cố định |
| `Coupon/Enums/CouponAppliesTo.php` | Áp cho bán, thuê, hoặc cả hai |
| `Coupon/Services/CouponService.php` | Kiểm tra mã, tính số giảm trên tiền hàng và tiền thuê, ghi nhận khi tạo đơn, gỡ khi hủy |
| `Review/Models/Review.php` | Điểm và nhận xét của một dòng đơn đã xong |
| `Review/Enums/ReviewKind.php` | Đánh giá mua hay thuê |
| `Review/Services/ReviewWriter.php` | Chỉ chủ đơn đánh giá dòng của đơn đó. Liệt kê review theo sản phẩm |
| `Chat/Models/Conversation.php` | Một thread giữa cửa hàng và một khách |
| `Chat/Models/Message.php` | Một tin. Biết tin do cửa hàng hay khách gửi |
| `Chat/Services/ChatService.php` | Mở thread, gửi tin, đánh dấu đã đọc, đếm chưa đọc, phân trang hộp thư |
| `Notification/Models/Notification.php` | Một dòng hàng đợi email |
| `Notification/Enums/NotificationType.php` | Loại sự kiện: đơn, thuê, nhắc trả |
| `Notification/Enums/NotificationChannel.php` | Kênh gửi, hiện là email |
| `Notification/Enums/NotificationStatus.php` | Chờ gửi, đã gửi, lỗi |
| `Notification/Services/NotificationWriter.php` | Xếp một thông báo một lần cho mỗi sự kiện, gửi, liệt kê theo user |
| `Notification/Mail/OutboundMail.php` | Khuôn email |
| `Notification/Jobs/SendOutboundEmail.php` | Job hàng đợi gọi writer để gửi |
| `Report/Services/ReportService.php` | Doanh thu, số đơn, thuê cho báo cáo quản trị |

Hai bảng `membership_plans` và `memberships` có trong migration gốc. Code PHP hiện không đọc chúng.

### `app/Gateway`

| File | Việc nó làm |
| --- | --- |
| `Services/CheckoutOrchestrator.php` | Checkout thật. Chuẩn bị dòng, áp mã, tính ship, trong một lần ghi tạo đơn, khóa kho, tạo lịch thuê và các khoản. Cũng chốt, hủy, đánh dấu đã thu |
| `Services/PaymentOrchestrator.php` | Thu một khoản, thu tiền mặt, xác nhận chuyển khoản, ghi MoMo đã trả về, hoàn tiền. Khi mọi khoản thu đã xong thì chuyển đơn sang đã thanh toán |
| `Services/MomoCheckoutService.php` | Tạo phiên MoMo, xử lý lúc khách quay về và lúc MoMo gọi IPN |
| `Momo/MomoClient.php` | Ký request và gọi API sandbox |
| `Services/ShippingService.php` | Hỏi phí và tạo hoặc hủy vận đơn |
| `Shipping/GhnClient.php` | Gọi API GHN: tỉnh, quận, phường, phí |
| `Services/NotificationOrchestrator.php` | Sau mỗi bước đơn hoặc thuê, quyết định xếp email nào |
| `Media/CloudinaryImageStore.php` | Upload ảnh lên Cloudinary, xóa ảnh cũ, tách public id từ URL |

### `app/Storefront` — trang khách

| File | Việc nó làm |
| --- | --- |
| `CatalogPageController.php` | `/` trang chủ và bán chạy. `/san-pham` danh sách có lọc. `/san-pham/{slug}` chi tiết |
| `AvailabilityPageController.php` | `/kho/ban`, `/kho/thue`, `/thue/bao-gia` |
| `PolicyPageController.php` | Ba trang chính sách thuê, đổi trả, cọc |
| `CartPageController.php` | Xem, thêm, xóa giỏ. Chỉ khách |
| `Support/CartService.php` | Giỏ lưu session |
| `Support/CartPresenter.php` | Tính tạm tính, giảm giá, cọc. Đổi giỏ thành dòng checkout |
| `Support/CartQtyGuard.php` | Chặn số lượng vượt tồn bán hoặc trùng món thuê cùng ngày |
| `CheckoutPageController.php` | Form thanh toán, tạo đơn, chi tiết đơn, xin gia hạn, gửi đánh giá. Checkout và đánh giá đòi email đã xác thực |
| `MyOrdersController.php` | `/don-cua-toi` (tab Mua / Thuê). `/don-hang` và `/lich-thue` redirect vào đây |
| `AuthPageController.php` | Đăng nhập, đăng ký khách, đăng xuất |
| `PasswordResetController.php` | Quên mật khẩu và đặt lại. Tối đa 6 lần mỗi phút |
| `EmailVerificationController.php` | Trang nhắc xác thực, link ký tên, gửi lại mail |
| `ProfilePageController.php` | Xem và sửa hồ sơ |
| `MessagePageController.php` | Mở thread và gửi tin |
| `NotificationPageController.php` | Hộp thông báo |
| `ShippingLookupController.php` | JSON tỉnh, quận, phường, phí cho `checkout-ghn.js` |
| `MomoCallbackController.php` | `start` mở thanh toán. `returned` khi khách quay về. `ipn` khi MoMo xác nhận |

### `app/Http` — admin

| File | Việc nó làm |
| --- | --- |
| `Controllers/Controller.php` | Controller gốc Laravel |
| `Middleware/EnsureCustomer.php` | Chặn tài khoản cửa hàng vào giỏ và trang khách |
| `Middleware/EnsureAdmin.php` | Chỉ `staff` và `admin` vào `/admin` |
| `Middleware/EnsureOwnerAdmin.php` | Chỉ `admin` vào user, mã giảm, báo cáo |
| `Support/PosCartService.php` | Giỏ quầy, session riêng, nhiều phiếu, nhớ tên và số điện thoại khách |
| `Admin/DashboardController.php` | Số liệu tổng quan |
| `Admin/CategoryController.php` | CRUD danh mục |
| `Admin/SportController.php` | CRUD môn |
| `Admin/ProductController.php` | CRUD sản phẩm và ảnh |
| `Admin/InventoryController.php` | Sửa tồn bán, thêm món thuê, đổi trạng thái món |
| `Admin/PosController.php` | Quầy: phiếu, thêm dòng, thanh toán hoặc lưu đơn chờ |
| `Admin/OrderController.php` | Chốt, đánh dấu đã thu, hủy, hoàn tất, cập nhật giao hàng |
| `Admin/PaymentController.php` | Danh sách khoản, lưu QR, xác nhận đã nhận, hoàn tiền |
| `Admin/FinanceController.php` | Đối soát, giao dịch, xuất CSV, sửa trạng thái COD |
| `Admin/RentalController.php` | Xác nhận, giao đồ, gia hạn, thu rồi duyệt, hoàn cọc, ghi phiếu trả |
| `Admin/ReviewController.php` | Xem và xóa đánh giá |
| `Admin/MessageController.php` | Hộp thư, trả lời, chủ động nhắn một khách |
| `Admin/UserController.php` | Tạo nhân viên hoặc quản trị, khóa hoặc mở tài khoản |
| `Admin/CouponController.php` | CRUD mã giảm |
| `Admin/ReportController.php` | Đưa số của `ReportService` ra view |

### `app/Console` và `app/Providers`

| File | Việc nó làm |
| --- | --- |
| `NotifyRentalsCommand.php` | Đánh dấu lịch quá hạn rồi xếp email nhắc hạn và quá hạn |
| `ReleaseExpiredReservationsCommand.php` | Hủy đơn online còn `pending` khi giữ chỗ bán đã hết hạn, rồi nhả kho |
| `AppServiceProvider.php` | Tiêm giỏ POS vào quầy. Gắn route model `{item}`, `{booking}`, `{payment}`, `{conversation}`. Đếm số món trong giỏ và hội thoại chưa đọc trên layout |

### `resources/views`

| File | Việc nó làm |
| --- | --- |
| `storefront/layouts/app.blade.php` | Header, menu, badge giỏ và tin nhắn |
| `layouts/admin.blade.php` | Sidebar quản trị |
| `layouts/pos.blade.php` | Khung quầy, không dùng sidebar admin |
| `partials/css.blade.php`, `js.blade.php` | Nhúng CSS và JS theo trang |
| `partials/vendor-head.blade.php`, `vendor-scripts.blade.php` | Bootstrap, font, icon |
| `partials/bank_transfer_qr.blade.php` | Khối QR và thông tin chuyển khoản |
| `partials/rental_compensation.blade.php` | Form tiền bồi thường lúc trả |
| `storefront/home.blade.php` | Banner và sản phẩm nổi bật |
| `storefront/catalog.blade.php` | Lưới sản phẩm và bộ lọc |
| `storefront/product.blade.php` | Ảnh, giá, chọn mua hoặc thuê và chọn ngày |
| `storefront/partials/product-card.blade.php` | Thẻ sản phẩm dùng lại |
| `storefront/cart.blade.php` | Các dòng giỏ và tạm tính |
| `storefront/checkout.blade.php` | Nhận hàng, GHN, MoMo, chuyển khoản, COD, mã giảm |
| `storefront/my-orders/index.blade.php` + `partials/purchases`, `rentals` | Đơn của tôi (Mua / Thuê) |
| `storefront/orders/show.blade.php` | Chi tiết một đơn |
| `storefront/partials/rental-extension-form.blade.php` | Form xin gia hạn |
| `storefront/account/profile.blade.php` | Sửa hồ sơ |
| `storefront/auth/login.blade.php`, `register.blade.php` | Đăng nhập, đăng ký |
| `storefront/auth/forgot-password.blade.php`, `reset-password.blade.php` | Quên và đặt lại mật khẩu |
| `storefront/auth/verify-email.blade.php` | Nhắc bấm link trong mail |
| `storefront/partials/password-field.blade.php` | Ô mật khẩu có nút hiện hoặc ẩn |
| `storefront/messages/show.blade.php` | Khung chat |
| `storefront/notifications/index.blade.php` | Danh sách thông báo |
| `storefront/policies/rental.blade.php`, `returns.blade.php`, `deposit.blade.php` | Chính sách |
| `admin/dashboard.blade.php` | Thẻ số và biểu đồ |
| `admin/categories/`, `sports/` | `index`, `create`, `edit` |
| `admin/products/` | `index`, `create`, `edit`, `_form` dùng chung |
| `admin/inventory/index.blade.php` | Tồn bán và danh sách món thuê |
| `admin/pos/index.blade.php` | Quầy ba cột |
| `admin/pos/_pay_modal.blade.php` | Popup thu tiền |
| `admin/pos/_desk_query.blade.php` | Giữ id phiếu đang mở trên URL |
| `admin/orders/index.blade.php`, `show.blade.php` | Lọc đơn và nút chốt, thu, hủy, giao hàng |
| `admin/payments/index.blade.php` | Khoản chờ xác nhận và form lưu QR |
| `admin/finance/index.blade.php`, `transactions.blade.php`, `_filters.blade.php` | Đối soát, từng khoản, form lọc |
| `admin/rentals/index.blade.php`, `show.blade.php` | Danh sách và một lịch thuê |
| `admin/reviews/index.blade.php` | Bảng đánh giá |
| `admin/messages/index.blade.php`, `show.blade.php` | Hộp thư và trả lời |
| `admin/users/index.blade.php`, `create.blade.php` | Tài khoản cửa hàng |
| `admin/coupons/` | `index`, `create`, `edit`, `_form` |
| `admin/reports/index.blade.php` | Bảng số liệu quản trị |

### `public/`

| File | Việc nó làm |
| --- | --- |
| `index.php` | File Apache gọi đầu tiên, nạp Laravel |
| `.htaccess` | Đẩy mọi URL về `index.php` |
| `robots.txt` | Hướng dẫn crawler |
| `css/storefront/base.css` | Header, menu, khung trang khách |
| `css/storefront/catalog.css` | Lưới và bộ lọc |
| `css/storefront/product.css` | Trang chi tiết |
| `css/storefront/checkout.css` | Giỏ và thanh toán |
| `css/storefront/chat.css` | Khung chat khách |
| `css/admin/base.css` | Sidebar quản trị |
| `css/admin/dashboard.css` | Thẻ số tổng quan |
| `css/admin/pos.css` | Ba cột quầy |
| `css/admin/finance.css` | Bảng đối soát |
| `css/admin/chat.css` | Khung chat admin |
| `css/shared/bank-qr.css` | Khối QR |
| `css/shared/password-toggle.css` | Nút hiện hoặc ẩn mật khẩu |
| `css/shared/viewport.css` | Vùng nhìn màn nhỏ |
| `js/storefront/chrome.js` | Menu và tương tác khung khách |
| `js/storefront/product.js` | Tab mua hoặc thuê và chọn ngày |
| `js/storefront/checkout-ghn.js` | Gọi `/giao-hang/...` để đổ tỉnh, quận, phường, phí |
| `js/storefront/password-toggle.js` | Hiện hoặc ẩn mật khẩu |
| `js/admin/dashboard-charts.js`, `finance-charts.js` | Biểu đồ |
| `js/admin/pos.js` | Thêm dòng và đổi số lượng tại quầy |
| `js/admin/product-form.js` | Thêm biến thể và xem trước ảnh |
| `js/admin/rental-return.js` | Hiện bồi thường theo tình trạng trả |
| `js/shared/copy.js` | Copy mã đơn hoặc số tài khoản |
| `js/shared/viewport.js` | Chỉnh vùng nhìn |
| `vendor/fonts/be-vietnam-pro.css` | Font chữ |
| `vendor/fontawesome/css/` | Icon |
| `media/images/` | Ảnh demo: áo gym, giày bóng đá, vợt tennis |

### `database/`, `tests/`, `docker/`, `storage/`

| File | Việc nó làm |
| --- | --- |
| `database/create_databases.sql` | Lệnh tạo database |
| `database/dumps/webthethao_schema.sql` | Bản dump schema |
| `migrations/2026_08_22_100000_create_webthethao_schema.php` | Tạo toàn bộ bảng gốc |
| `migrations/2026_09_02_150000_replace_payment_simulation_with_cash.php` | Đổi phương thức mô phỏng thành tiền mặt |
| `migrations/2026_09_02_163500_create_payment_qr_settings_table.php` | Bảng cấu hình QR |
| `migrations/2026_09_24_100000_add_upgrade_shipping_and_gateway.php` | Cột ship, mã GHN, bảng `gateway_sessions` |
| `migrations/2026_09_25_100000_create_conversations_and_messages_tables.php` | Bảng chat |
| `migrations/2026_09_28_100000_create_sessions_table.php` | Session trên MySQL |
| `migrations/2026_09_28_100100_create_cache_table.php` | Cache trên MySQL |
| `seeders/DatabaseSeeder.php` | Ba tài khoản, mã `SALE10`, rồi catalog và kho |
| `seeders/AdminUserSeeder.php` | Chỉ tài khoản quản trị lúc deploy |
| `seeders/ProductCatalogSeeder.php` | Môn, danh mục, sản phẩm, biến thể demo |
| `seeders/InventorySeeder.php` | Tồn bán và món thuê demo |
| `factories/*.php` | Mỗi model chính một factory cho PHPUnit |
| `tests/TestCase.php` | Boot Laravel cho mọi test |
| `tests/Unit/RentalPricerTest.php` | Công thức giá ngày và tuần |
| `tests/Unit/ProductImageSrcTest.php` | URL ảnh local và Cloudinary |
| `tests/Feature/ExampleTest.php` | Trang chủ trả 200 |
| `tests/Feature/Auth/AuthWebTest.php` | Đăng ký, đăng nhập, chặn sai vai trò |
| `tests/Feature/Auth/PasswordResetTest.php` | Gửi link và đổi mật khẩu |
| `tests/Feature/Storefront/StorefrontPageTest.php` | Trang chủ, catalog, chi tiết, chính sách |
| `tests/Feature/Storefront/BestSellerTest.php` | Khối bán chạy |
| `tests/Feature/Storefront/CheckoutWebTest.php` | Thêm giỏ và tạo đơn |
| `tests/Feature/Storefront/MomoPaymentTest.php` | Mở phiên và nhận IPN |
| `tests/Feature/Storefront/ShippingCodTest.php` | Giao nhà và COD |
| `tests/Feature/Storefront/AccountUpgradeTest.php` | Sửa hồ sơ |
| `tests/Feature/Storefront/RentalScheduleTest.php` | Tab Thuê trong Đơn của tôi (ownership) |
| `tests/Feature/Storefront/MessageTest.php` | Gửi tin hai chiều |
| `tests/Feature/Storefront/PromoReviewReportTest.php` | Áp mã và gửi đánh giá |
| `tests/Feature/Notification/NotificationWebTest.php` | Xếp và đọc thông báo |
| `tests/Feature/Admin/AdminCrudTest.php` | Tạo danh mục, môn, sản phẩm |
| `tests/Feature/Admin/InventoryIndexTest.php` | Màn kho |
| `tests/Feature/Admin/PosTest.php` | Thêm dòng quầy và thu tiền |
| `tests/Feature/Admin/CloseoutFlowsTest.php` | Chốt, thu, hủy đơn online |
| `tests/Feature/Admin/PaymentQrTest.php` | Lưu QR và xác nhận khoản |
| `tests/Feature/Admin/AdminFinanceTest.php` | Lọc và xuất |
| `tests/Feature/Admin/RentalLifecycleTest.php` | Từ xác nhận đến trả và hoàn cọc |
| `tests/Feature/Admin/ReportTest.php` | Báo cáo chỉ quản trị vào được |
| `tests/Feature/Admin/CloudinaryProductImageTest.php` | Upload ảnh khi đã cấu hình |
| `tests/Feature/Deploy/AdminSeedTest.php` | Seed deploy không nhân đôi user |
| `docker/nginx.conf`, `php.ini`, `php-fpm.conf` | Web server và PHP trong container |
| `docker/entrypoint.sh` | Lúc container chạy thì migrate và seed |
| `docker/render.env.example` | Mẫu biến trên Render |
| `docker/check-ca.php` | Kiểm tra chứng chỉ HTTPS khi gọi MoMo và GHN |
| `storage/app/public` | Ảnh sản phẩm local |
| `storage/framework/` | Cache, session file, Blade đã biên dịch |
| `storage/logs/` | `laravel.log` |

---

## 10. Vòng đời một request

Apache nhận URL rồi `.htaccess` đẩy về `public/index.php`. Laravel đọc `bootstrap/app.php`, nạp route, chạy middleware, rồi mới tới controller.

```text
Trình duyệt
  → public/index.php
    → bootstrap/app.php
      → routes/web.php          chọn controller theo URL và middleware
        → Controller            nhận form, kiểm tra quyền
          → Gateway hoặc Domain service
            → Eloquent Model
              → MySQL webthethao
        → Blade trong resources/views
          → HTML + CSS/JS trong public/
```

Middleware theo lớp:

1. Nhóm `guest`: chỉ người chưa đăng nhập vào form đăng nhập, đăng ký, quên mật khẩu.
2. Nhóm `auth`: đã đăng nhập. Dùng cho xác thực email và đăng xuất.
3. Nhóm `customer`: user phải là khách. Nhân viên mở giỏ website sẽ bị chặn.
4. Nhóm `verified`: khách đã bấm link trong email. Checkout, mở MoMo và gửi đánh giá nằm ở đây.
5. Nhóm `admin`: `staff` hoặc `admin` vào `/admin`.
6. Nhóm `owner`: chỉ `admin` vào user, mã giảm, báo cáo.

Ví dụ mở trang chi tiết. `GET /san-pham/{slug}` không cần đăng nhập. `CatalogPageController::show` gọi `CatalogService::productBySlug`. Model `Product` kéo biến thể, môn, danh mục, đánh giá. Controller trả `storefront/product.blade.php`. Layout nhúng `product.css` và `product.js`.

Ví dụ bấm thanh toán. `POST /thanh-toan` đi qua `auth`, `customer`, `verified`. `CheckoutPageController::store` đọc giỏ từ `CartService`, gửi payload cho `CheckoutOrchestrator::checkout`. Orchestrator không render HTML. Nó ghi đơn rồi controller redirect sang trang đơn. Blade chỉ vẽ kết quả.

Lỗi nghiệp vụ ném `ValidationException`. Laravel trả lại form kèm thông báo, không ghi nửa đơn. Nếu ghi đơn rồi bước sau lỗi, `compensate()` dọn phần đã tạo.

---

## 11. Luồng nghiệp vụ chính

### Đăng nhập và vai trò

Khách tự đăng ký ở `/dang-ky`. `AuthPageController` tạo user `customer` và gửi mail xác thực. Nhân viên và quản trị không tự đăng ký. Quản trị tạo họ ở `/admin/users`.

Đăng nhập dùng session Laravel (`Auth::attempt`), không dùng JWT. Sau khi đăng nhập:

- Khách vào giỏ, hồ sơ, đơn, chat, lịch thuê.
- Checkout vẫn khóa cho đến khi email được xác thực.
- `staff` vào `/admin` trừ user, mã giảm, báo cáo.
- `admin` vào hết các màn đó.
- Tài khoản cửa hàng không mua trên website. Khách không tạo đơn tại quầy.

Quên mật khẩu: `/quen-mat-khau` gửi link, `/dat-lai-mat-khau` đổi mật khẩu, tối đa 6 lần mỗi phút. `ResetPasswordNotification` soạn mail.

### Giỏ hàng

Giỏ khách nằm trong session qua `CartService`. Giỏ quầy là `PosCartService`, session khác, có nhiều phiếu.

Thêm dòng bán: `CartQtyGuard` cộng số lượng đang có trong giỏ với số vừa thêm, so với `AvailabilityService`. Vượt tồn thì từ chối.

Thêm dòng thuê: bắt buộc có ngày bắt đầu và kết thúc. Cùng một món không được chồng khoảng ngày đã có trong giỏ. `RentalSession` nhớ khoảng ngày khách đang chọn trên trang chi tiết.

`CartPresenter` tính tiền hàng, tiền thuê, cọc và số giảm để Blade hiện. Cọc không nằm trong số được giảm.

### Checkout online

`CheckoutOrchestrator::checkout` làm lần lượt:

1. Kênh là `web`. Người thao tác phải là khách và chính là chủ đơn.
2. `prepareLines` với từng dòng:
   - Bán: sản phẩm phải `sale` hoặc `both`, có giá bán, còn đủ tồn.
   - Thuê: sản phẩm phải `rental` hoặc `both`, có giá ngày. `RentalPricer` tính tiền. `AvailabilityService` phải còn ít nhất một món trống.
3. Nếu có mã, `CouponService::quote` tính giảm trên tiền hàng và tiền thuê. Không giảm cọc, không giảm phí ship.
4. `quoteShipping`: nhận tại shop thì phí 0. Giao nhà thì lấy phí GHN theo tỉnh, quận, phường.
5. Trong một lần ghi:
   - `OrderWriter::create` tạo đơn `pending` và các dòng.
   - `lockAndBook`: giữ số lượng bán, hoặc giữ một món thuê rồi tạo `RentalBooking` trạng thái chờ.
   - `PaymentWriter::createIntents` tạo khoản tiền hàng (cộng phí ship, trừ giảm giá) và khoản cọc riêng nếu có.
   - `CouponService::redeem` ghi lượt dùng mã.
6. `NotificationOrchestrator` xếp email.
7. Nếu bước sau lỗi, `compensate()` hủy phần đã ghi.

Đơn online lúc này là `pending`. Kho bị khóa nhưng chưa trừ hẳn. Quầy mới là chỗ chốt.

### Trạng thái đơn

`OrderStatus::allowedNext()`:

```text
pending → confirmed | cancelled
confirmed → paid | cancelled
paid → processing | completed | cancelled
processing → completed | cancelled
completed → (dừng)
cancelled → (dừng)
```

- `pending`: chờ quán chốt. Giữ chỗ bán có hạn. `webthethao:release-expired` hủy đơn online còn pending khi hạn giữ chỗ đã qua, nhả kho và gỡ mã.
- `confirmed`: quán đã chốt (`confirmByShop`). Kho vẫn đang giữ.
- `paid`: đã thu đủ. `markPaid` trừ kho thật (`commitByOrder`) và chuyển lịch thuê sang đã xác nhận. Đơn giao nhà chưa có mã GHN thì tạo vận đơn.
- `processing`: đang giao hoặc đang thuê. Đơn quầy có lịch thuê được chuyển sang đây ngay sau khi thu, vì đồ đã giao tại quầy.
- `completed`: xong. Đơn thuê có thể tự sang đây khi mọi lịch đã trả.
- `cancelled`: nhả kho, hủy lịch thuê còn mở, hủy khoản đang chờ, hoàn khoản đã thu, gỡ mã, hủy vận đơn nếu GHN còn cho hủy.

Đơn đã giao đồ thuê không hủy bằng nút hủy đơn. Phải trả đồ hoặc hoàn tiền riêng.

### Thanh toán

Mỗi đơn có thể có nhiều khoản `payments`:

- `merchandise`: tiền hàng + tiền thuê − giảm giá + phí ship.
- `deposit`: tiền cọc, không bị mã giảm.
- `refund`: khoản hoàn.

Trạng thái khoản: `pending` → `completed`, `failed`, hoặc `cancelled`.

Cách thu:

- **MoMo.** Khách bấm thanh toán trên đơn đã xác thực email. `MomoCheckoutService` tạo `gateway_sessions` và chuyển sang trang MoMo. Khách quay về bằng `returned`. MoMo gọi `ipn` (route này không kiểm tra CSRF). Chữ ký đúng và kết quả thành công thì `PaymentOrchestrator` đánh dấu khoản đã thu. Khi mọi khoản cần thu đã xong, đơn sang `paid`.
- **Chuyển khoản.** `BankTransferQr` dựng QR theo số tiền và nội dung. Khách tự chuyển. Nhân viên đối chiếu rồi bấm xác nhận trên `/admin/payments`. QR không tự chốt.
- **COD.** Dùng khi giao nhà. Khoản ở trạng thái chờ cho đến khi giao xong và quán xác nhận đã thu.
- **Tiền mặt.** Dùng tại quầy. Thu ngay trong popup thanh toán.

`PaymentOrchestrator::settleOrderIfReady` chỉ chuyển đơn sang `paid` khi không còn khoản phải thu ở trạng thái chờ.

### Bán tại quầy

`PosController` dùng `PosCartService`. Nhân viên mở nhiều phiếu, thêm dòng, đổi số lượng. Bấm thanh toán gọi cùng `CheckoutOrchestrator` với kênh `pos`.

Khác online:

- Người thao tác phải là tài khoản cửa hàng.
- Chủ đơn là khách được chọn trong payload, không phải nhân viên.
- Thanh toán đủ thì đơn thành `paid` ngay, kho bị trừ, lịch thuê được xác nhận rồi kích hoạt vì khách nhận đồ tại quầy.
- Lưu đơn thì giữ `pending`, chưa thu.

Giỏ website của khách không bị đụng.

### Giao hàng GHN

`checkout-ghn.js` gọi lần lượt:

- `GET /giao-hang/tinh`
- `GET /giao-hang/quan`
- `GET /giao-hang/phuong`
- `GET /giao-hang/phi`

`ShippingLookupController` chuyển cho `ShippingService` rồi `GhnClient`. Phí được lưu vào đơn lúc checkout.

Trạng thái giao trên đơn: nhận tại quầy, chờ tạo vận đơn, chờ lấy, đang giao, đã giao, đã hủy, lỗi. Nhân viên cập nhật ở trang chi tiết đơn. Hủy vận đơn GHN chỉ khi còn `pending` hoặc `ready_to_pick`.

Vận đơn được tạo khi đơn giao nhà chuyển sang `paid` mà chưa có `ghn_order_code`.

### Thuê, gia hạn, trả

Trạng thái lịch `BookingStatus`:

```text
pending → confirmed | cancelled
confirmed → active | cancelled
active → returned | overdue
overdue → returned
returned → (dừng)
cancelled → (dừng)
```

- Checkout tạo lịch `pending` và giữ món trong khoảng ngày.
- Đơn sang `paid` thì lịch sang `confirmed`.
- Quầy thu xong, hoặc nhân viên bấm giao đồ, thì lịch sang `active` và món sang `rented`.
- Trước hạn trả ~24h: `webthethao:notify-rentals` lúc 8:00 xếp email nhắc trả (`NOTIFICATION_DUE_REMINDER_DAYS`, mặc định 1). Đến hạn chưa trả: cùng lệnh gọi `markOverdue` (`active` → `overdue`) và xếp email quá hạn.
- Trả đồ: nhân viên ghi `ReturnCondition`. Món sang `inspecting`. Lịch sang `returned`. Nếu hỏng, ghi `RentalIncident` và tiền bồi thường. Phần cọc được hoàn là cọc trừ bồi thường. `OrderWriter::completeIfRentalsReturned` hoàn tất đơn khi không còn lịch mở.

Gia hạn:

1. Khách hoặc nhân viên xin ngày kết thúc mới. `RentalExtension` ở `pending`. Ngày mới phải sau ngày hiện tại và món vẫn trống.
2. Nhân viên thu thêm tiền thuê rồi duyệt, hoặc duyệt nếu đã thu. Trạng thái sang `approved`, ngày kết thúc lịch được đẩy ra.
3. Từ chối thì `rejected`, lịch giữ ngày cũ.

Hủy đơn khi lịch còn `pending` hoặc `confirmed` thì lịch sang `cancelled` và món được nhả. Lịch `active` hoặc `overdue` không hủy theo đơn.

### Mã giảm và đánh giá

`CouponService::usable` kiểm tra mã còn hạn, còn lượt, đơn đạt mức tối thiểu, và mã có áp cho loại dòng trong giỏ. `quote` trả số tiền giảm. `redeem` ghi `coupon_redemptions` sau khi đơn tạo thành công. Hủy đơn thì `unredeem`.

Đánh giá chỉ sau khi đơn đã ở trạng thái cho phép và dòng thuộc đơn của khách đang đăng nhập. Một dòng một review. Quản trị xóa review ở `/admin/reviews`.

### Chat và email

Mỗi khách một `Conversation`. `ChatService::forCustomer` mở thread nếu chưa có. Tin của khách và tin của cửa hàng cùng bảng `messages`. Composer storefront/admin cho phép đính kèm thẻ xem trước **sản phẩm** hoặc **đơn hàng** (`attachment_type` / `attachment_id` / `attachment_payload` snapshot). Khách chỉ đính đơn của mình; nhân viên chỉ đính đơn của khách trong hội thoại. Layout đếm tin chưa đọc: khách thấy số tin cửa hàng chưa đọc, admin thấy số hội thoại chưa đọc.

Email transactional (đơn mới) và nhắc thuê không gửi thẳng trong request/command body. `NotificationWriter::enqueueOnce` ghi một dòng `notifications` với `dedupe_key` unique, rồi `SendOutboundEmail` gửi. Mail xác thực / quên mật khẩu vẫn đi qua Laravel auth mailer.

#### Email nhắc hạn trả (return reminder)

| Thành phần | Vai trò |
| --- | --- |
| `routes/console.php` | Cron Laravel: `webthethao:notify-rentals` mỗi ngày 08:00 `Asia/Ho_Chi_Minh`, `withoutOverlapping` |
| Docker `entrypoint` | Vòng `schedule:run` mỗi phút trên container web |
| `NotificationOrchestrator::dispatchRentalCycle` | Chọn lịch `confirmed`/`active` có `end_date` = hôm nay + `NOTIFICATION_DUE_REMINDER_DAYS` (mặc định 1 ≈ 24h), nhóm theo `user + order + end_date`, rồi `markOverdue` + mail quá hạn |
| Blade `resources/views/emails/rental-due-reminder.blade.php` | Template HTML nhắc trả; quá hạn dùng `rental-overdue.blade.php` |
| `NotificationWriter` | Idempotency theo `dedupe_key` (cột unique + payload), retry khi `failed`, log `notification.email.*` / `notification.rental_cycle.*` |

Chạy tay: `php artisan webthethao:notify-rentals` hoặc `--date=YYYY-MM-DD` để giả lập ngày chạy.

### Việc chạy nền

`routes/console.php` đặt lịch, cần scheduler của máy chủ thực sự chạy `php artisan schedule:run`.

- `webthethao:notify-rentals` mỗi ngày lúc 8:00 (nhắc trả + quá hạn).
- `webthethao:release-expired` mỗi 15 phút, chỉ đụng đơn online còn `pending` có giữ chỗ bán đã hết hạn.
