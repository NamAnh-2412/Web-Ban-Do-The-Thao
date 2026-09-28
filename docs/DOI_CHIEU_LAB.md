# Đối chiếu `lar_vidu1` ↔ WebTheThao

Cập nhật **2026-09-25**. Không copy giỏ/thuê shop trái cây. Chỉ mang cách gọi API, chữ ký, tách giao dịch.

File nâng cấp ưu tiên: [NANG_CAP.md](NANG_CAP.md). Nhật ký MoMo/GHN hôm nay: mục 4.

---

## 1. Tổng quan `lar_vidu1` (shop lab)

Laravel 12, Blade, Bootstrap 5, session Auth, MySQL `ecommerce2024`. Role: `admin` / `customer`. Test: 54 feature (Lab 09).

### Module chức năng

| Module | Việc | Bảng / logic |
| --- | --- | --- |
| Catalog admin | CRUD danh mục, sản phẩm, variant (SKU, size, màu, tồn) | `categories`, `products`, `product_variants` |
| Cửa hàng | Trang `/`, chi tiết SP (cần login + verify) | Session cart |
| Auth | Đăng ký/nhập, `MustVerifyEmail`, SMTP | `users.role`, `email_verified_at` |
| Giỏ / checkout | Thêm–sửa SL–xóa, chọn SP, SĐT `0\d{9}` | Session, trừ tồn khi đặt |
| Đơn khách | Lịch sử, hủy, tạo lại GHN, MoMo lại | `orders`, `order_items` |
| GHN | Tỉnh–quận–phường, phí server, tạo/hủy vận đơn | `to_district_id`, `to_ward_code`, `ghn_order_code`, `ghn_total_fee` |
| MoMo + COD | Sandbox, callback + IPN, `PaymentTransaction` | Nhiều lần thử / đơn; fail không tạo đơn mới |
| Chat | Một thread cửa hàng ↔ khách | `conversations`, `messages` |
| Admin đơn (Lab 08) | Lọc tab vận, hủy, đổi shipping, thu COD khi giao | `shipping_status` |
| Admin user | CRUD, không xóa chính mình / admin cuối / user có đơn | — |
| Báo cáo (Lab 08) | Doanh thu đã thu, Chart.js | Không đếm đơn hủy/hoàn |
| Finance (Lab 09) | Thống kê + danh sách + CSV + cập nhật COD | Một giao dịch đại diện / đơn (ưu tiên paid/hoàn) |

### Tích hợp API

| Cổng | Base | Việc |
| --- | --- | --- |
| GHN test | `dev-online-gateway.ghn.vn/shiip/public-api` | `master-data/*`, `/v2/shipping-order/fee`, `/create`, `/v2/switch-status/cancel` |
| MoMo sandbox | `test-payment.momo.vn/v2/gateway/api/create` | Tạo phiên, HMAC, return + IPN (trừ CSRF) |
| SMTP | `.env` MAIL | Mail verify |

**GHN (2026-09-25):** shop test `216755` `location: []`; `shop/update` không map. Tạo vận đơn **thành công** khi gửi `from_*` + `return_*` trên từng đơn (đã verify live, mã thử hủy). Không nhận trùng phường kho.

**MoMo lab:** tách `payment_transactions`; chữ ký HMAC; thanh toán lại cùng đơn. Lab `requestType = payWithCC` — WebTheThao dùng cùng kiểu.

### Cấu hình lab cần có trên `.env`

`GHN_TOKEN`, `GHN_SHOP_ID`, `GHN_FROM_*`, `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `APP_URL`, SMTP. Không commit secret.

---

## 2. Khoảng trống WebTheThao so với lab

WebTheThao **đã có sẵn** (không làm lại): POS, thuê–cọc–gia hạn, coupon, review, môn/danh mục/lọc catalog, 3 vai trò, báo cáo bán/thuê, chính sách, kho món thuê.

| Lab có | WebTheThao | Ghi chú |
| --- | --- | --- |
| MoMo tự `paid` + trả lại | **Có** — `MomoCheckoutService`, `payWithCC`, `gateway_sessions` | Cùng `requestType` với lab |
| GHN địa chỉ + phí + mã | **Có** — `ShippingService`, checkout giao nhà | 2026-09-25: thêm `return_*` + chặn trùng phường kho |
| COD giao nhà | **Có** | Cọc gộp `cod_amount` |
| Verify email | **Có** — `MustVerifyEmail`, chặn checkout/review | — |
| Hồ sơ `/tai-khoan` | **Có** | Lab **không** có |
| Bán chạy trang chủ | **Có** | Lab **không** có |
| Chat 1 thread | **Có** — `ChatService`, `/tin-nhan`, `/admin/messages` | Badge chưa đọc |
| Finance Lab 09 | **Có** — `FinanceService`, `/admin/finance` | 1 giao dịch/đơn trên `payments`; CSV; COD tay qua `PaymentOrchestrator` (không `cod_paid`) |
| Báo cáo Chart.js nhiều kỳ | Dashboard 7 ngày | Lab có ngày/tháng/năm + theo cổng |
| Admin CRUD user đầy đủ | Owner tạo/khóa user | Lab sửa/xóa có ràng buộc đơn |
| Lọc catalog | **Có** (môn, size, màu, giá) | Lab **không** có |

### Route / config còn thiếu hoặc cần soi

- Có `/tin-nhan`, `/admin/messages`, `/admin/finance`.
- `.env.example` đã có `MOMO_*` / `GHN_*` — máy local phải điền token/shop; `GHN_FROM_WARD_CODE` + `GHN_FROM_DISTRICT_ID` bắt buộc nếu kho shop rỗng.
- IPN MoMo: `localhost` không nhận webhook; demo dùng return. CSRF except `thanh-toan/momo/ipn`.
- Không copy `FinanceController` lab nguyên khối — nếu làm, gắn `Payment` / `GatewaySession` Domain, không `orders.status` kiểu `cod_paid`.

---

## 3. Việc còn đáng làm (sau Finance 2026-09-25)

1. GHN webhook `delivering` / `delivered` (chưa có cả hai project).  
2. Không: wishlist, loyalty, blog, MoMo production, copy giỏ lab.

---

## 4. Nhật ký 2026-09-25 — MoMo & GHN

### Lab `lar_vidu1`

- **GHN:** root cause xác nhận — kho shop rỗng, không sửa được qua API. Fix app: `GHNOrderService::pickupAddress()` gửi `from_*` và `return_*`. Preview/create live OK. Chặn nhận = phường kho. Ghi `lar_vidu1/docs/GHN_PENDING.md`.
- **MoMo:** không đổi merchant hôm nay. Vẫn tách giao dịch, callback/IPN, trả lại cùng đơn. Nhắc: dùng `payWithATM` cho thẻ `9704…0018` / `0026`.
- **Lab 09 Finance:** thống kê + giao dịch + CSV + cập nhật COD (không sửa MoMo tay).

### WebTheThao (cùng ngày)

- `ShippingService::createShipment` đã có `from_*`; **bổ sung** `return_*` và từ chối trùng phường kho — cùng bài học lab.
- MoMo dùng `payWithCC` + `PaymentOrchestrator` + `GatewaySession` (cùng form cổng với lab).
- Chat một thread: `app/Domain/Chat`, không copy Blade shop trái cây.
- Tài liệu cũ [CONG_THANH_TOAN.md](CONG_THANH_TOAN.md) ghi “chưa làm” — đã cập nhật: sandbox **đã gắn**.
