# WebTheThao — Yêu cầu và kế hoạch

Đồ án **một môn: thương mại điện tử**.

Website **bán và cho thuê** dụng cụ, quần áo, thiết bị thể thao. Một cửa hàng (single-vendor).

**Công nghệ: Laravel** (PHP), Blade, Bootstrap, session `Auth`, một MySQL. Không làm đồ án hướng dịch vụ (SOA), không nhiều database, không API Gateway / JWT.

Form admin / đăng nhập học **kiểu** bài lab `lar_vidu1` (card, `form-control`, `@error`, bảng CRUD, `_form` dùng chung). Không copy code nghiệp vụ từ lab hay SportShop.

- Thư mục: `c:\xampp\htdocs\WebTheThao`

## 1. Mục tiêu nghiệp vụ

- Vừa **bán** vừa **cho thuê** trên cùng catalog (`offer_mode` = sale | rental | both).
- Giỏ hỗn hợp, checkout, thanh toán (tiền hàng và cọc tách khoản).
- Thuê: lịch ngày, giá ngày/tuần, cọc, gia hạn, trả đồ, sự cố.
- Admin quản lý bằng form/bảng Blade trên Laravel.

## 2. Kiến trúc (Laravel thuần)

Một ứng dụng Laravel, một database MySQL `webthethao`. Eloquent + khóa ngoại. Giao diện Blade + Bootstrap 5. Đăng nhập session Laravel (`Auth::attempt`, middleware `auth` / `admin`). Ảnh lưu file `storage/app/public/products`, DB chỉ lưu path.

```text
Trình duyệt
    → Storefront / Admin (Blade)
        → Controller Laravel
            → Eloquent / service nội bộ
                → MySQL webthethao
```

## 3. Công cụ

1. XAMPP: Apache + MySQL `127.0.0.1:3306`, `root`, mật khẩu trống.
2. phpMyAdmin: tạo DB `webthethao`.
3. `php artisan migrate` rồi `php artisan db:seed`.
4. `php artisan storage:link` (đã tạo).
5. Mở `http://localhost/WebTheThao/public`.

Hai loại tài khoản, không gộp mua + bán trên cùng vai trò:

- **Khách** (`customer`): đăng ký trên website; chỉ mua / thuê / xem đơn của mình.
- **Cửa hàng** do quản trị tạo ở `/admin/users`, không tự đăng ký:
  - **Nhân viên** (`staff`): kho, **bán tại quầy**, đơn, thanh toán, lịch thuê, đánh giá. Chốt / thu / hủy đơn online.
  - **Quản trị** (`admin`): thêm người dùng cửa hàng, mã giảm, báo cáo.

Luồng đơn online: khách checkout → đơn `pending` (kho khóa) → quán **Chốt đơn** → `confirmed` → thu đủ → `paid`.

Luồng tại quầy: nhân viên **Bán tại quầy** → giao diện 3 cột (nhóm SP, thẻ sản phẩm, giỏ) → **Thanh toán** tạo đơn `channel=pos` và thu đủ (`paid`), hoặc **Lưu đơn** giữ `pending`. Không dùng giỏ website của khách.

Tài khoản mẫu sau seed: `admin@webthethao.test` / `password` (quản trị cửa hàng), `nhanvien@webthethao.test` / `password` (nhân viên), `khach@webthethao.test` / `password` (khách). Catalog seed là dữ liệu demo.

## 4. Chức năng đã có

**Khách:** catalog, chi tiết Mua/Thuê, giỏ, đăng nhập/đăng ký + verify email, hồ sơ, checkout (MoMo / CK / COD giao nhà), đơn, tin nhắn cửa hàng, thông báo email. Không vào `/admin`.

**Nhân viên** (`/admin`): danh mục, môn, sản phẩm, kho, **bán tại quầy**, đơn (chốt / thu / hủy), thanh toán, tài chính đối soát, lịch thuê, tin nhắn khách, đánh giá. Không vào Người dùng / mã giảm / báo cáo.

**Quản trị:** toàn bộ nhân viên + người dùng, mã giảm, báo cáo.

## 5. Lộ trình còn lại

- Review, mã giảm giá, báo cáo: đã có form/bảng Blade (admin + storefront).
- Gói nộp (ERD một schema, luồng bán / thuê / quán chốt, script demo): [ERD_VA_LUONG.md](ERD_VA_LUONG.md).
- Tùy chọn sau: chỉnh giao diện, animation — không đổi kiến trúc.
- Cổng thanh toán: MoMo sandbox tự chốt; CK/QR tĩnh + tiền mặt quầy xác nhận tay — [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md).
- Nâng cấp theo ưu tiên: [NANG_CAP.md](NANG_CAP.md). Đối chiếu lab/PDF: [HUONG_PHAT_TRIEN.md](HUONG_PHAT_TRIEN.md). Chat một thread đã có (`/tin-nhan`).

## 6. Không làm

- Kết hợp môn hướng dịch vụ (SOA, nhiều DB, Gateway, JWT, HTTP giữa module).
- Marketplace, app mobile, SMS, máy POS đầy đủ (quét mã, in bill), ảnh BLOB trong MySQL, C++ bắt buộc.
