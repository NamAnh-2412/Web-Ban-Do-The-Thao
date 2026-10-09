<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f7;font-family:Georgia,'Times New Roman',serif;color:#1a1f24;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f7;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #dde3e8;">
                    <tr>
                        <td style="padding:28px 28px 12px;background:linear-gradient(135deg,#5c1f14 0%,#8a3522 100%);color:#fff8f5;">
                            <div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.85;">WebTheThao</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:700;">Thuê quá hạn trả</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Xin chào {{ $customerName }},</p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">
                                Lịch thuê #{{ $bookingId }} (đơn #{{ $orderId }}) đã quá hạn.
                                Ngày trả dự kiến: <strong>{{ $endDate }}</strong>.
                                Vui lòng liên hệ cửa hàng để trả đồ và xử lý phí phát sinh.
                            </p>

                            @if (! empty($productName))
                                <p style="margin:0 0 20px;font-size:15px;line-height:1.45;padding:14px 16px;background:#faf3f0;border:1px solid #efd7ce;">
                                    <strong>{{ $productName }}</strong>
                                    @if (! empty($sku))
                                        <br><span style="color:#5b6770;font-size:13px;">SKU: {{ $sku }}</span>
                                    @endif
                                </p>
                            @endif

                            @if (! empty($scheduleUrl))
                                <p style="margin:0 0 20px;">
                                    <a href="{{ $scheduleUrl }}" style="display:inline-block;background:#8a3522;color:#ffffff;text-decoration:none;padding:12px 18px;font-size:15px;font-weight:700;">
                                        Xem đơn thuê của tôi
                                    </a>
                                </p>
                            @endif

                            <p style="margin:0;font-size:13px;line-height:1.5;color:#5b6770;">
                                Đây là email tự động từ hệ thống nhắc trả.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
