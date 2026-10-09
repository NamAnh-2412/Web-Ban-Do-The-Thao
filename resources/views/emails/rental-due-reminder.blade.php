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
                        <td style="padding:28px 28px 12px;background:linear-gradient(135deg,#0f3d2e 0%,#1f6b4f 100%);color:#f4fff8;">
                            <div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.85;">WebTheThao</div>
                            <h1 style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:700;">Nhắc hạn trả đồ thuê</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">Xin chào {{ $customerName }},</p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">
                                Đơn thuê #{{ $orderId }} đến hạn trả vào
                                <strong>{{ $endDate }}</strong>
                                (còn khoảng {{ $daysUntilDue }} ngày).
                                Vui lòng trả đúng hạn để tránh phí trễ và giữ tiền cọc.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;border-top:1px solid #e6ebef;">
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding:12px 0;border-bottom:1px solid #e6ebef;font-size:15px;line-height:1.45;">
                                            <div style="font-weight:700;">{{ $item['name'] }}</div>
                                            @if (! empty($item['sku']))
                                                <div style="color:#5b6770;font-size:13px;margin-top:4px;">SKU: {{ $item['sku'] }}</div>
                                            @endif
                                            <div style="color:#5b6770;font-size:13px;margin-top:4px;">
                                                Lịch #{{ $item['booking_id'] }}
                                                · {{ $item['start_date'] }} → {{ $item['end_date'] }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            @if (! empty($scheduleUrl))
                                <p style="margin:0 0 20px;">
                                    <a href="{{ $scheduleUrl }}" style="display:inline-block;background:#1f6b4f;color:#ffffff;text-decoration:none;padding:12px 18px;font-size:15px;font-weight:700;">
                                        Xem đơn thuê của tôi
                                    </a>
                                </p>
                            @endif

                            <p style="margin:0;font-size:13px;line-height:1.5;color:#5b6770;">
                                Đây là email tự động từ hệ thống nhắc trả. Nếu bạn đã trả đồ, vui lòng bỏ qua.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
