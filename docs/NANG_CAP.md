# WebTheThao — Kế hoạch nâng cấp

Việc **quan trọng và bắt buộc** (PDF thầy *Yêu cầu các chức năng bài tập lớn*) để lên đầu. Việc phụ / còn thời gian để dưới.

Không làm lại POS, thuê–cọc, coupon, review, lọc catalog. Không copy giỏ shop trái cây `lar_vidu1` — chỉ mang cách gọi MoMo, GHN, verify email, chat một thread.

Đọc kèm: [YEU_CAU_VA_KE_HOACH.md](YEU_CAU_VA_KE_HOACH.md), [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md), [DOI_CHIEU_LAB.md](DOI_CHIEU_LAB.md) (audit lab 2026-09-25), [HUONG_PHAT_TRIEN.md](HUONG_PHAT_TRIEN.md).

---

## Thứ tự ưu tiên

| # | Việc | PDF thầy | Vì sao xếp đây | Trạng thái |
| --- | --- | --- | --- | --- |
| 1 | Cổng MoMo tự chốt đơn | Chính — thanh toán | Lỗ hổng lớn nhất khi bảo vệ: chưa có ví/cổng, checkout đang cứng CK | **Xong (sandbox)** |
| 2 | Trạng thái giao + GHN | Chính — theo dõi đơn đến khi giao; phụ — đối tác vận chuyển | Đơn mới có pending/confirmed/paid/completed, chưa đóng gói / đang giao / đã giao | **Xong (sandbox)** |
| 3 | Xác thực email | Chính — xác thực người dùng | Cột `email_verified_at` đã có, chưa bắt verify | **Xong** |
| 4 | Hồ sơ khách | Chính — xem/sửa thông tin | Thiếu hẳn trang tài khoản | **Xong** (`/tai-khoan`) |
| 5 | Bán chạy trên trang khách | Chính — hiển thị SP bán chạy | Admin đã có top SP, home chưa dùng | **Xong** |
| 6 | COD giao nhà | Chính — phương thức thanh toán | Quầy đã tiền mặt; web giao nhà chưa COD. Làm sau GHN | **Xong** |
| 7 | Chat cửa hàng ↔ khách | Phụ — live chat | Lab đã có mẫu; không bắt để bảo vệ | **Xong** (`/tin-nhan`, `/admin/messages`) |
| 8 | Finance kiểu Lab 09 | Phụ / đối soát | Lab 09: 1 giao dịch/đơn, CSV, COD tay | **Xong** (`/admin/finance`) |
| — | Coupon, review, báo cáo, POS, thuê | Chính / nghiệp vụ đồ án | Đã có — không làm lại | Xong |

Làm **1 → 5** là đủ ý yêu cầu chính PDF còn thiếu. **6–7** chỉ khi còn thời gian — chat đã làm 2026-09-25.

---

## 1. Cổng MoMo (làm trước)

Checkout online hiện luôn `payment_method = bank_transfer`. Nhân viên phải bấm xác nhận. Enum `momo` / `vnpay` đã có, chưa gắn merchant.

**Làm**

- Checkout: chọn **MoMo** hoặc giữ CK/QR.
- Service tạo phiên, kiểm chữ ký, gọi `PaymentOrchestrator::complete` — cùng ý lab 6, **không** nhét controller lab vào storefront.
- Return (có session) + IPN (bỏ CSRF). Cùng một đơn: fail thì **Thanh toán lại**, không tạo đơn mới.
- MoMo thành công: tự `paid` (và `confirm` nếu đang `pending`). Quầy vẫn thu tay.
- Tiền hàng và cọc thuê vẫn tách khoản; thu một lần rồi phân bổ, ghi rõ trong code.
- `.env` sandbox lab (`MOMO_*`). Không commit secret. Test `Http::fake`, không gọi mạng thật.

**Thẻ sandbox lab:** form thẻ quốc tế `payWithCC` (cùng `requestType` với `lar_vidu1`). OTP `OTP`.

**Xong khi:** đặt hàng → cổng test → `/don-hang` `paid`; thẻ khóa có nút trả lại; chữ ký sai bị từ chối.

**Không làm:** MoMo production, tự `paid` khi chỉ quét ảnh QR tĩnh.

Chi tiết nghiên cứu cổng: [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md).

---

## 2. Theo dõi đơn đến khi giao + GHN

PDF bắt trạng thái: đang xử lý → đã đóng gói → đang vận chuyển → đã giao. Địa chỉ khách đang một ô text, không phí, không mã vận đơn.

**Làm**

- Tách trạng thái giao trên đơn bán giao nhà (map GHN: `ready_to_pick` / `delivering` / `delivered`…), hiện trên đơn khách và admin.
- Checkout: **Nhận tại quầy** (không GHN) hoặc **Giao nhà** (dropdown tỉnh–quận–phường, tính phí **trên server**).
- Tổng = tiền hàng (+ cọc nếu có) + ship. Ship không ăn coupon.
- Admin thấy mã vận đơn; hủy đơn thì hủy GHN khi còn `ready_to_pick` / `pending`.
- POS / nhận tại quầy: không tạo vận đơn.

**Môi trường:** [5sao.ghn.dev](https://5sao.ghn.dev/), `GHN_TOKEN`, `GHN_SHOP_ID`, `GHN_FROM_*`. Shop lab `216755` kho rỗng — **không** cần shop mới nếu payload có `from_*` + `return_*` (lab đã tạo được vận đơn 2026-09-25). Vẫn nên tránh nhận trùng phường kho.

**Thuê:** chỉ ship chiều đi ở bước này.

**Xong khi:** đổi địa chỉ ra phí thật; đơn giao nhà có mã GHN; khách thấy trạng thái đang giao / đã giao.

---

## 3. Xác thực email

PDF: tạo tài khoản + xác thực người dùng.

**Làm**

- `MustVerifyEmail` cho `customer`. Chặn checkout và đánh giá khi chưa verify.
- Nhân viên / quản trị: seed sẵn `email_verified_at`, không bắt quy trình khách.
- SMTP như lab 3 Plus. `APP_URL=http://localhost/WebTheThao/public`. Hướng dẫn Gmail: [XAC_THUC_EMAIL.md](XAC_THUC_EMAIL.md).

**Xong khi:** chưa verify không đặt được hàng; bấm link trong mail thì mua được.

---

## 4. Hồ sơ khách

PDF: xem và chỉnh sửa thông tin cá nhân. Cả lab lẫn đồ án đều chưa có trang này.

**Làm**

- `/tai-khoan`: sửa tên, SĐT, đổi mật khẩu.
- Không cho khách đổi email đã verify (hoặc đổi thì verify lại). Không đổi role.

**Xong khi:** khách sửa được hồ sơ, reload vẫn giữ.

---

## 5. Sản phẩm bán chạy (trang khách)

PDF: hiển thị sản phẩm bán chạy. Báo cáo admin đã có `top_products`; home đang lấy 8 SP bất kỳ.

**Làm**

- Khối **Bán chạy** trên trang chủ (và/hoặc catalog): cộng SL / doanh thu từ `OrderItem` đơn đã thu, loại dòng thuê/cọc, tối đa 8.
- Hết hàng vẫn hiện, gắn nhãn hết hàng — không ẩn để “làm đẹp số”.

**Xong khi:** home có danh sách theo đơn thật, không hard-code.

---

## 6. COD giao nhà (sau mục 2)

Quầy đã tiền mặt. PDF muốn nhiều phương thức; lab 6: COD + tạo vận đơn.

**Làm**

- Thêm COD **chỉ khi giao nhà**. `cod_amount` = số còn phải thu (cọc thu trước hay gộp — ghi rõ).
- Không COD khi nhận tại quầy.

**Xong khi:** đơn COD có mã GHN trên admin.

---

## 7. Chat cửa hàng ↔ khách (phụ)

PDF xếp live chat ở yêu cầu phụ.

**Làm nếu còn giờ:** một `Conversation` / khách; staff + admin trả lời `/admin/messages`; badge chưa đọc. Học lab, đặt service trong Domain — không copy view shop trái cây.

**Xong khi:** khách gửi được; admin/staff trả lời cùng thread; badge chưa đọc.

**Không làm:** ticket, chatbot, FAQ bắt buộc, blog, wishlist, loyalty, đăng nhập Google.

---

## Đã có — không nâng cấp lại

Catalog (danh mục, môn, variant, bán/thuê), giỏ hỗn hợp, khóa kho, đơn chờ chốt, CK/QR + xác nhận tay, POS, thuê–cọc–gia hạn–trả–sự cố, coupon, review, báo cáo, chính sách thuê/đổi/cọc, 3 vai trò.

---

## Không làm

- Copy `lar_vidu1` đè giỏ / thuê.
- MoMo/VNPay production, GPKD.
- Wishlist, gợi ý, tích điểm, blog, ticket, social login, phân tích hành vi.
- SOA, nhiều DB, JWT, marketplace, app mobile.

---

## Demo bảo vệ (khi xong mục 1–5)

1. Đăng ký → verify mail → sửa hồ sơ; trang chủ thấy bán chạy.
2. Đặt MoMo thẻ `0018` → đơn `paid` không cần nhân viên.
3. Thẻ `0026` → fail → thanh toán lại, cùng một đơn.
4. (Nếu có mục 2) Giao nhà + GHN, khách thấy đang giao.
5. (Tuỳ) Chat: khách gửi `/tin-nhan`, nhân viên trả lời `/admin/messages`.
6. POS / QR tĩnh vẫn xác nhận tay — đối chiếu với cổng tự chốt.

---

## Nhật ký 2026-09-25 (MoMo / GHN)

Chi tiết đối chiếu module: [DOI_CHIEU_LAB.md](DOI_CHIEU_LAB.md).

**GHN lab:** shop `216755` `location: []`, `shop/update` fail. Fix: gửi địa chỉ lấy/trả trên từng create. Live create OK. WebTheThao `ShippingService` đã có `from_*`; cùng ngày thêm `return_*` và chặn trùng phường kho.

**MoMo:** lab không đổi khóa hôm nay. WebTheThao dùng `payWithCC` như lab (cùng form cổng). Return + IPN + thanh toán lại cùng đơn.

**Lab 09:** Finance trên `lar_vidu1` (`/admin/finance`). Đồ án chưa làm trang đó; không chặn bảo vệ.
