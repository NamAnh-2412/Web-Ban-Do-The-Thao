# Cổng thanh toán

Cập nhật **2026-09-25**. Sandbox **đã gắn MoMo** (không production). CK/QR tĩnh + tiền mặt quầy vẫn xác nhận tay.

## Đã làm (sandbox)

- Checkout: MoMo / CK / COD (COD chỉ giao nhà).
- `MomoCheckoutService` + `MomoClient`: `payWithCC` như lab, HMAC, `GatewaySession`.
- Return `/thanh-toan/momo/return` + IPN `/thanh-toan/momo/ipn` (trừ CSRF).
- Thành công: tự `paid` qua `PaymentOrchestrator` — không đợi nhân viên.
- Fail: cùng đơn, nút thanh toán lại (`forceNew`).
- `.env`: `MOMO_*`. Không commit secret. PHPUnit: `Http::fake`.

**Thẻ test:** form cổng MoMo giống lab (`payWithCC`). OTP `OTP`.

Ảnh QR treo **không** tự chốt (không webhook).

## Bài học lab 2026-09-25

- `requestType = payWithCC` như `lar_vidu1` — form thẻ quốc tế, không phải ATM Napas.
- IPN không tới `localhost`; demo return là đủ.
- Một đơn nhiều phiên — idempotent theo `gateway_order_id`.

Xem [DOI_CHIEU_LAB.md](DOI_CHIEU_LAB.md), [NANG_CAP.md](NANG_CAP.md).

## Không làm

GPKD, MoMo/VNPay production, tự `paid` khi chỉ quét QR tĩnh.
