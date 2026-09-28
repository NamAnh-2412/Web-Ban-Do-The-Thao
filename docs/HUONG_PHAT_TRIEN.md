# WebTheThao — Hướng phát triển

**Việc cần làm, xếp theo ưu tiên:** [NANG_CAP.md](NANG_CAP.md). File này giữ đối chiếu lab / PDF và ghi chú kỹ thuật.

Tài liệu này định hướng **nâng cấp sau phần đồ án đã có**, đối chiếu:

- Lab Laravel `lar_vidu1` (catalog, auth + xác thực email, giỏ, GHN, COD/MoMo, Lab 08 admin đơn/user/báo cáo Chart.js, tin nhắn cửa hàng↔khách).
- File thầy gửi *Yêu cầu các chức năng bài tập lớn.pdf* (yêu cầu chính bắt buộc + yêu cầu phụ không bắt buộc).

Không viết lại những gì WebTheThao đã làm xong. Không copy nguyên code shop trái cây vào Domain/Gateway — chỉ mang **cách gọi API, chữ ký, tách giao dịch, luồng chat một thread**.

Đọc kèm: [YEU_CAU_VA_KE_HOACH.md](YEU_CAU_VA_KE_HOACH.md), [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md), [ERD_VA_LUONG.md](ERD_VA_LUONG.md).

---

## 1. Hiện trạng (không làm lại)

| Nhóm | WebTheThao đã có | Lab `lar_vidu1` |
| --- | --- | --- |
| Catalog | Danh mục, môn, sản phẩm, variant, `offer_mode` (bán / thuê / cả hai) | CRUD danh mục + sản phẩm + variant |
| Tài khoản | Khách / nhân viên / quản trị, session Auth | Register, login, role admin/customer |
| Giỏ & đơn | Giỏ hỗn hợp, checkout, khóa kho, chốt tay | Session cart, chọn SP thanh toán |
| Thanh toán | MoMo sandbox tự `paid`, CK/QR tĩnh, tiền mặt quầy, COD giao nhà | MoMo sandbox + COD, tự chốt |
| Giao hàng | Nhận tại quầy / giao nhà GHN (tỉnh–quận–phường, phí, mã vận đơn) | GHN tỉnh–quận–phường, phí, mã vận đơn |
| Email | Hàng đợi thông báo + `MustVerifyEmail` khách | SMTP + bắt buộc xác thực email |
| Admin đơn / user / báo cáo | Đơn chốt–thu–hủy, user cửa hàng, báo cáo bảng + Chart.js 7 ngày | Lab 08: lọc đơn, CRUD user, bảng + biểu đồ Chart.js |
| Chat | Một hội thoại cửa hàng ↔ khách (`/tin-nhan`) | Một hội thoại cửa hàng ↔ khách |
| Điểm riêng đồ án | POS, thuê–cọc–gia hạn–trả–sự cố, coupon, review, báo cáo | Không có |

Checkout online: MoMo / CK / COD (COD chỉ giao nhà). Enum `momo` đã gắn sandbox. `MustVerifyEmail` đã bật cho khách.

Luồng online hiện tại:

```text
Khách đặt → pending (kho khóa)
  → MoMo thành công: tự confirm + paid
  → COD giao nhà: GHN ngay, khoản COD pending đến khi giao
  → CK/QR: quán Chốt đơn → confirmed → thu đủ → paid
```

---

## 2. Nguyên tắc khi mang lab sang

1. Giữ kiến trúc Domain + `PaymentOrchestrator` / `CheckoutOrchestrator`. Không nhét `User/GHNController` kiểu bài tập vào storefront nếu có thể đặt service trong `app/Gateway` hoặc `app/Domain`.
2. Tách **đơn hàng** và **giao dịch cổng** (bài học lab 6): payload, `provider_txn_id`, chữ ký, thanh toán lại không tạo đơn mới.
3. Tiền hàng và **cọc thuê** vẫn tách khoản. Cổng có thể thu một lần (tổng) rồi phân bổ, hoặc hai phiên — quyết định ở pha 1, ghi rõ trong code.
4. GHN chỉ cho đơn **giao nhà**. `Nhận tại quầy` / POS không tạo vận đơn.
5. Sandbox (MoMo test, GHN `5sao.ghn.dev`) cho đồ án. Production cần hộ kinh doanh — không nằm trong lộ trình này.
6. `localhost` không nhận IPN/webhook. Demo callback trình duyệt; muốn IPN đủ thì dùng ngrok/HTTPS public.
7. Không SOA, không nhiều DB, không JWT, không marketplace — đúng [YEU_CAU_VA_KE_HOACH.md](YEU_CAU_VA_KE_HOACH.md) mục 6.

---

## 3. Lộ trình theo pha

Thứ tự: chốt **yêu cầu chính PDF thầy còn thiếu** (nhanh) → cổng MoMo (lab đã chạy) → GHN/COD → chat (yêu cầu phụ).

### Pha 0 — Hồ sơ, bán chạy, xác thực email (BTL bắt buộc, làm nhanh)

**Vì sao:** PDF thầy bắt hồ sơ khách, hiển thị sản phẩm bán chạy, xác thực người dùng. WebTheThao đã có cột `email_verified_at` và top sản phẩm trên **báo cáo admin**, chưa đưa ra storefront / chưa cho khách sửa hồ sơ.

**Làm gì**

- Trang `/tai-khoan`: xem/sửa tên, SĐT, đổi mật khẩu (không cho đổi role).
- Trang chủ / catalog: khối **Bán chạy** lấy từ `OrderItem` đã thu (bán, không gồm cọc), giới hạn 8.
- `MustVerifyEmail` cho `customer`; chặn checkout / đánh giá khi chưa verify. Nhân viên–admin seed sẵn `email_verified_at`.
- SMTP: học `.env` lab 3 Plus; `APP_URL` đúng `http://localhost/WebTheThao/public`.

**Không làm ở pha này:** wishlist, gợi ý ML, đăng nhập Google.

### Pha 1 — MoMo sandbox tự chốt đơn (ưu tiên cổng thanh toán)

**Vì sao:** Đồ án đã chừa chỗ ([CONG_THANH_TOAN.md](CONG_THANH_TOAN.md)). Lab 6 vừa chạy được (tạo phiên, ATM test, callback, fail + thanh toán lại).

**Làm gì**

- Checkout: chọn MoMo (giữ CK/QR làm dự phòng).
- `MomoService`: `createPayment`, kiểm chữ ký, `markPaid` / `markFailed` — cùng ý lab, gọi `PaymentOrchestrator::complete`.
- Route return (có session) + IPN (bỏ CSRF), idempotent theo `gateway_order_id`.
- Đơn MoMo thành công: tự `paid` (và `confirm` nếu đang `pending`), không bắt nhân viên bấm.
- Thất bại: đơn cũ, nút **Thanh toán lại**; không tạo đơn mới.
- `.env`: `MOMO_*` sandbox đề lab; không commit secret production.
- Test: Http::fake chữ ký đúng/sai, không tạo đơn trùng, phân biệt tiền hàng / cọc.

**Thẻ test ATM (sandbox)**

| Kết quả | Số thẻ | Hạn | Tên | OTP |
| --- | --- | --- | --- | --- |
| Thành công | `9704 0000 0000 0018` | `03/07` hoặc `12/30` | `NGUYEN VAN A` | `OTP` |
| Thẻ khóa | `9704 0000 0000 0026` | như trên | `NGUYEN VAN A` | `OTP` |

Dùng `requestType = payWithCC` như lab `lar_vidu1` (form thẻ trên cổng MoMo).

**Không làm ở pha này:** MoMo production, tự chốt khi quét ảnh QR tĩnh.

---

### Pha 2 — GHN giao nhà + phí ship

**Vì sao:** Địa chỉ đang 1 ô text, không phí, không mã vận đơn. Lab 5 đã có tỉnh → quận → phường → tính phí → tạo đơn GHN.

**Làm gì**

- Checkout: dropdown địa chỉ GHN; lưu `to_district_id`, `to_ward_code`, `ghn_total_fee`.
- Tính lại phí **trên server** lúc đặt (không tin số hidden form).
- Tổng thanh toán = tiền hàng (+ cọc nếu có) + phí ship. Phí ship không ăn coupon (cùng nguyên tắc coupon không trừ cọc).
- Tùy chọn nhận: **Tại quầy** (không GHN) / **Giao nhà** (bắt địa chỉ GHN).
- Nhân viên xem mã vận đơn trên admin đơn; hủy đơn gọi hủy GHN khi còn `ready_to_pick` / `pending`.
- Shop test: [5sao.ghn.dev](https://5sao.ghn.dev/), `GHN_TOKEN`, `GHN_SHOP_ID`, địa chỉ kho người gửi, `GHN_FROM_DISTRICT_ID`.
- Lab shop `216755` kho rỗng; 2026-09-25 tạo vận đơn được nhờ `from_*` + `return_*` (xem [DOI_CHIEU_LAB.md](DOI_CHIEU_LAB.md)). Không bắt buộc đổi ShopId.

**Thuê:** ưu tiên ship chiều đi. Chiều khách trả đồ — làm sau nếu còn thời gian (pha 5).

---

### Pha 3 — COD online (giao nhà)

**Vì sao:** Quầy đã thu tiền mặt. Khách đặt web + giao nhà chưa có COD. Lab 6: COD → tạo vận đơn ngay.

**Làm gì**

- Checkout: MoMo | CK | **COD** (chỉ khi giao nhà).
- COD: đơn `cod_ordered` / tương đương, GHN `cod_amount` = số còn phải thu (cọc có thể thu trước hoặc gộp — ghi rõ).
- Không thu COD cho nhận tại quầy (trùng POS).

---

### Pha 4 — Chat cửa hàng ↔ khách (yêu cầu phụ PDF: live chat)

**Vì sao:** Lab đã có một `Conversation` / khách, badge chưa đọc. PDF xếp live chat ở **yêu cầu phụ**, không bắt buộc.

**Làm gì nếu còn thời gian:** một thread / khách, staff+admin trả lời trong `/admin/messages`. Không làm ticket helpdesk, không chatbot.

Đã chuyển xác thực email lên **pha 0**.

---

### Pha 5 — Mở rộng nếu còn thời gian

- Nhật ký cổng: payload request/response trên `payments` (hoặc bảng con), đối soát khi khách báo đã trừ tiền.
- GHN webhook cập nhật `delivering` / `delivered` → gợi ý admin hoàn tất đơn bán.
- Thuê: ship chiều trả; bồi thường sự cố + hoàn cọc một phần (đã có form sự cố — chỉ nối thanh toán).
- VNPay sandbox **hoặc** PayOS — **một** cổng thêm, không làm cả hai. Ưu tiên xong MoMo trước.
- Thông báo: mail “đã giao GHN”, “MoMo thành công” (hàng đợi notification đã có).
- FAQ / trang Giới thiệu–Liên hệ (CMS tĩnh, PDF phụ). Không làm blog hay loyalty.

---

## 4. Việc ngoài code (môi trường)

| Việc | Ghi chú |
| --- | --- |
| MoMo | Bộ sandbox đề lab, không cần GPKD |
| GHN | Tài khoản [5sao.ghn.dev](https://5sao.ghn.dev/), tạo kho, copy Token + ShopId |
| IPN | Ngrok nếu cần chứng minh webhook; demo đồ án dùng callback là đủ |
| SMTP | App password Gmail như lab 3 Plus |
| `.env` | Không commit token/secret; mirror `.env.example` (key rỗng) |

---

## 5. Thứ tự demo khi bảo vệ (sau khi làm pha 0–3)

1. Đăng ký → mail verify → sửa hồ sơ; trang chủ thấy bán chạy.
2. Khách đặt giao nhà, chọn địa chỉ GHN, thấy phí ship.
3. MoMo thẻ `0018` → đơn `paid` không cần nhân viên.
4. MoMo thẻ `0026` → fail, **Thanh toán lại**, cùng một đơn.
5. COD → mã vận đơn GHN trên admin.
6. POS tiền mặt / QR tĩnh: giữ như hiện tại (đối chiếu “cổng tự chốt” vs “quầy xác nhận tay”).

---

## 6. Ràng buộc kỹ thuật gợi ý

- Gắn MoMo vào `PaymentOrchestrator::complete`, không bypass kho/cọc.
- Webhook: `lockForUpdate`, bỏ qua nếu đã `paid`.
- GHN: `config/services.php` (hoặc `config/shipping.php`) + `.env`; `verify_ssl=false` chỉ môi trường test XAMPP.
- Test feature: `Http::fake` GHN/MoMo; không gọi mạng thật trong PHPUnit.
- Form checkout: `regex` SĐT `0\d{9}` như lab 6 nếu thống nhất với shop.

---

## 7. Không nằm trong hướng này

- Đăng ký doanh nghiệp MoMo/VNPay production.
- Tự `paid` khi khách chỉ quét ảnh QR tĩnh (không có IPN).
- Copy nguyên `lar_vidu1` (Fruit Shop, session cart thô) đè lên giỏ/thuê WebTheThao.
- App mobile, SMS, máy POS quét mã/in bill, marketplace nhiều shop.
- SOA / API Gateway / JWT giữa module.

---

## 8. Tiêu chí “xong từng pha”

| Pha | Xong khi |
| --- | --- |
| 0 BTL nhanh | Khách sửa hồ sơ; trang chủ có bán chạy; chưa verify không checkout |
| 1 MoMo | Đặt hàng → cổng test → về `/don-hang` với `paid`; fail có nút trả lại; test chữ ký pass |
| 2 GHN | Đổi tỉnh/quận/phường ra phí; đơn giao nhà có mã GHN khi tạo vận đơn thành công |
| 3 COD | Đơn giao nhà COD lên GHN, admin thấy mã |
| 4 Chat | Khách gửi được; admin/staff trả lời cùng thread; badge chưa đọc |
| 5 | Từng mục tự chọn, có test hoặc kịch bản demo ghi trong ERD |

Cập nhật file này khi một pha đã merge (ghi ngày + commit/ghi chú ngắn).

Pha 0–4 và Finance Lab 09 đã làm **2026-09-24/25**. GHN webhook chưa làm.

---

## 9. Đối chiếu PDF thầy (2026-09-18)

Nguồn: `Yêu cầu các chức năng bài tập lớn.pdf` (3 trang). PDF là checklist TMĐT **chung**, không bắt shop trái cây hay WebTheThao copy nhau.

### Yêu cầu chính — WebTheThao

| Nhóm PDF | Đã có | Thiếu / một phần |
| --- | --- | --- |
| SP chi tiết, danh mục, tồn kho | Có | — |
| Bộ lọc + tìm theo từ khóa / size / màu / giá | Có (`CatalogService`) | — |
| Sản phẩm bán chạy | Top SP trên **báo cáo admin** + khối **Bán chạy** trang chủ | — |
| Đăng ký / đăng nhập / vai trò | Khách, nhân viên, quản trị | — |
| Xác thực người dùng | `MustVerifyEmail` khách | — |
| Hồ sơ xem / sửa | `/tai-khoan` | — |
| Giỏ + đặt hàng | Có (hỗn hợp bán/thuê) | Không copy giỏ lab |
| Cổng thanh toán (thẻ / ví / CK) | MoMo sandbox tự `paid`; CK/QR tĩnh xác nhận tay | Không production |
| Đơn: lịch sử, admin xác nhận/hủy | Có + trạng thái giao GHN | — |
| Đánh giá sau đơn thành công | Có | — |
| Thống kê doanh thu, SP bán chạy | Báo cáo + Chart.js 7 ngày | Trang báo cáo chưa đủ biểu đồ kiểu lab (theo tháng/năm) |

### Yêu cầu phụ — không bắt để bảo vệ

| PDF phụ | WebTheThao | Gợi ý |
| --- | --- | --- |
| Wishlist, gợi ý, lịch sử duyệt | Chưa | Bỏ nếu hết thời gian |
| Mã giảm / voucher | **Đã có** | Giữ |
| Loyalty / tích điểm | Chưa | Không làm |
| Email xác nhận đơn / khuyến mãi | Hàng đợi notification | Nối mail MoMo/GHN ở pha 5 |
| FAQ, ticket, blog | Chính sách thuê/đổi/cọc | FAQ tĩnh nếu còn giờ; không blog |
| Live chat | `/tin-nhan` + `/admin/messages` | Đã làm pha 4 |
| Vận chuyển đối tác | GHN sandbox | `from_*` + `return_*`; không nhận trùng phường kho |
| Social login, phân tích hành vi | Chưa | Không làm |

Lab `lar_vidu1` **không** thay đồ án: thiếu review, coupon, POS, thuê, lọc catalog. Lab **có** MoMo, GHN, verify email, chat, Chart.js admin — mang kỹ thuật, không mang UI shop trái cây.
