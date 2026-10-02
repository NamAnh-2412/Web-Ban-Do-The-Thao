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
│   │   ├── MessagePageController.php           # Chat với cửa hàng
│   │   ├── RentalScheduleController.php        # Lịch thuê của khách
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
├── docs/                                       # Tài liệu đồ án: yêu cầu, ERD, thanh toán, mail
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

Tài liệu đồ án (ERD, luồng, script demo): [docs/README.md](docs/README.md)
