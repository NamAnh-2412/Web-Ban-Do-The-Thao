@extends('layouts.admin')
@section('title', 'Báo cáo')
@section('content')
    <h1 class="page-title">Báo cáo</h1>
    <p class="page-subtitle mb-4">Đơn tạo trong khoảng ngày. Doanh thu = bán + thuê − giảm; cọc tách riêng, không cộng vào doanh thu.</p>

    <form class="admin-card p-3 mb-4 row g-2 align-items-end" method="get">
        <div class="col-md-3">
            <label class="form-label" for="from">Từ ngày</label>
            <input class="form-control" type="date" id="from" name="from" value="{{ $from }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">Đến ngày</label>
            <input class="form-control" type="date" id="to" name="to" value="{{ $to }}">
        </div>
        <div class="col-md-3">
            <button class="btn btn-success">Lọc</button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Đơn tạo', $order_total],
            ['Đơn đã thu', $order_paid],
            ['Đơn hủy', $order_cancelled],
            ['Doanh thu', number_format($revenue, 0, ',', '.').'đ'],
        ] as [$label, $value])
            <div class="col-md-3">
                <div class="admin-card p-4">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="admin-card p-4 h-100">
                <h2 class="h5">Doanh thu</h2>
                <div>Bán: {{ number_format($merchandise, 0, ',', '.') }}đ</div>
                <div>Thuê: {{ number_format($rental, 0, ',', '.') }}đ</div>
                <div>Giảm giá: −{{ number_format($discount, 0, ',', '.') }}đ</div>
                @if ($other_refunds > 0)
                    <div>Hoàn (không phải cọc): −{{ number_format($other_refunds, 0, ',', '.') }}đ</div>
                @endif
                <div class="fw-bold mt-2">Doanh thu: {{ number_format($revenue, 0, ',', '.') }}đ</div>
                <div class="small text-muted mt-2">Online {{ number_format($online_revenue, 0, ',', '.') }}đ · Tại quầy {{ number_format($pos_revenue, 0, ',', '.') }}đ</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="admin-card p-4 h-100">
                <h2 class="h5">Cọc</h2>
                <div>Đã thu: {{ number_format($deposit, 0, ',', '.') }}đ</div>
                <div>Đã hoàn: {{ number_format($deposit_refunded, 0, ',', '.') }}đ</div>
                <div class="fw-bold mt-2">Chưa hoàn: {{ number_format($deposit_outstanding, 0, ',', '.') }}đ</div>
                <div class="small text-muted mt-2">Cọc đang giữ hoặc giữ lại khi trả trễ / hư. Không tính vào doanh thu.</div>
                <div class="small mt-3">Tổng khách trả lúc đặt (gồm cọc): {{ number_format($collected, 0, ',', '.') }}đ</div>
            </div>
        </div>
    </div>

    <div class="small text-muted mb-4">Đánh giá trong kỳ: {{ $reviews_count }} · điểm TB {{ $reviews_avg ?: '—' }}</div>

    <div class="admin-card mb-4">
        <div class="p-3 fw-semibold">Sản phẩm bán / thuê nhiều</div>
        <p class="small text-muted px-3 mb-0">Theo dòng đơn, chưa trừ mã giảm, không gồm cọc.</p>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>Sản phẩm</th><th>Loại</th><th>SL</th><th>Doanh thu dòng</th></tr></thead>
            <tbody>
            @forelse ($top_products as $row)
                <tr>
                    <td>{{ $row->product_name }}</td>
                    <td>{{ $row->line_type === 'sale' ? 'Mua' : 'Thuê' }}</td>
                    <td>{{ $row->qty }}</td>
                    <td>{{ number_format($row->revenue, 0, ',', '.') }}đ</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Chưa có đơn đã thu trong kỳ.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-card mb-4">
        <div class="p-3 fw-semibold">Mã giảm đã dùng</div>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>Mã</th><th>Số tiền giảm</th><th>Ngày</th></tr></thead>
            <tbody>
            @forelse ($coupons as $row)
                <tr>
                    <td>{{ $row->coupon->code ?? '—' }}</td>
                    <td>{{ number_format($row->discount_amount, 0, ',', '.') }}đ</td>
                    <td>{{ $row->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-3">Chưa dùng mã nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-card">
        <div class="p-3 fw-semibold">Tồn kho thấp</div>
        <table class="table admin-table align-middle mb-0">
            <thead><tr><th>Mã SKU</th><th>Sản phẩm</th><th>Còn</th><th>Ngưỡng</th></tr></thead>
            <tbody>
            @forelse ($low_stocks as $stock)
                <tr>
                    <td>{{ $stock->variant->sku ?? '—' }}</td>
                    <td>{{ $stock->variant->product->name ?? '—' }}</td>
                    <td>{{ $stock->availableQuantity() }}</td>
                    <td>{{ $stock->low_stock_threshold }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Không có mã dưới ngưỡng.</td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
@endsection
