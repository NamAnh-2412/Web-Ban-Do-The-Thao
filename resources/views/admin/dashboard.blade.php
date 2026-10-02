@extends('layouts.admin')
@include('partials.css', ['file' => 'css/admin/dashboard.css'])
@include('partials.js', ['file' => 'vendor/chartjs/chart.umd.min.js'])
@include('partials.js', ['file' => 'js/admin/dashboard-charts.js'])

@section('title', 'Tổng quan')

@section('content')
    <h1 class="page-title">Tổng quan</h1>
    <p class="page-subtitle mb-4">Việc cần làm: nhận đơn online, giao / trả đồ, theo dõi kho.</p>

    <div class="vp-grid vp-grid--fit mb-3">
        @foreach ([
            ['Đơn hàng hôm nay', $todayOrderCount, 'Tất cả đơn tạo hôm nay, trừ hủy'],
            ['Đơn mua', $todaySaleOrders, 'Chỉ bán — hôm nay'],
            ['Đơn thuê', $todayRentalOrders, 'Chỉ thuê — hôm nay'],
            ['Đơn hỗn hợp', $todayMixedOrders, 'Mua và thuê — hôm nay'],
        ] as [$label, $value, $hint])
            <div class="admin-card p-4 h-100">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fs-3 fw-bold">{{ $value }}</div>
                <div class="small text-muted mt-1">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="admin-card p-4 mb-4">
        <div class="fw-semibold mb-1">Doanh thu 7 ngày</div>
        <div class="small text-muted mb-3">Đơn đã thu (bán + thuê − giảm, không gồm cọc).</div>
        <canvas id="week-revenue-chart" height="90"></canvas>
    </div>

    <div class="vp-grid vp-grid--fit mb-3">
        @foreach ([
            ['Đơn online', $onlinePendingOrders, route('admin.orders.index', ['status' => 'pending', 'channel' => 'online', 'date' => 'all']), $onlinePendingOrders > 0],
            ['Cần giao đồ', $handoverBookings, route('admin.rentals.index', ['status' => 'confirmed']), $handoverBookings > 0],
            ['Quá hạn trả', $overdueBookings, route('admin.rentals.index', ['status' => 'overdue']), $overdueBookings > 0],
        ] as [$label, $value, $href, $alert])
            <div>
                <a href="{{ $href }}" class="text-decoration-none text-reset">
                    <div class="admin-card p-4 h-100 {{ $alert && $label === 'Quá hạn trả' ? 'border-danger' : '' }}">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-3 fw-bold {{ $alert && $label === 'Quá hạn trả' ? 'text-danger' : '' }}">{{ $value }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="vp-grid vp-grid--fit mb-4">
        @foreach ([
            ['Đang thuê', $activeBookings, route('admin.rentals.index', ['status' => 'active'])],
            ['SKU bán tồn thấp', $lowSaleCount, route('admin.inventory.index')],
            ['SKU thuê hết món', $emptyRentalCount, route('admin.inventory.index')],
        ] as [$label, $value, $href])
            <div>
                <a href="{{ $href }}" class="text-decoration-none text-reset">
                    <div class="admin-card p-4 h-100">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-3 fw-bold">{{ $value }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="vp-split mb-4">
        <div>
            <div class="admin-card">
                <div class="p-3 fw-semibold d-flex justify-content-between align-items-center">
                    <span>Đơn online</span>
                    <a class="small" href="{{ route('admin.orders.index', ['status' => 'pending', 'channel' => 'online', 'date' => 'all']) }}">Xem tất cả</a>
                </div>
                <table class="table admin-table mb-0">
                    <thead><tr><th>#</th><th>Khách</th><th>Tổng</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($onlinePendingRows as $order)
                        <tr>
                            <td>{{ $order->id }}</td>
                            <td>{{ $order->user?->name }}</td>
                            <td>{{ number_format($order->grand_total, 0, ',', '.') }}đ</td>
                            <td><a href="{{ route('admin.orders.show', $order) }}">Xem</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Không có đơn online chờ nhận.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <div class="admin-card">
                <div class="p-3 fw-semibold d-flex justify-content-between align-items-center">
                    <span>Lịch cần giao / quá hạn</span>
                    <a class="small" href="{{ route('admin.rentals.index') }}">Xem tất cả</a>
                </div>
                <table class="table admin-table mb-0">
                    <thead><tr><th>SKU</th><th>Khách</th><th>Trả</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($actionSessionRows as $session)
                        @php $booking = $session['lead']; @endphp
                        <tr>
                            <td>{{ $booking->variant?->sku }}@if ($session['item_count'] > 1) <span class="text-muted">+{{ $session['item_count'] - 1 }}</span>@endif</td>
                            <td>{{ $booking->user?->name }}</td>
                            <td>{{ $booking->end_date->toDateString() }}</td>
                            <td>
                                <a href="{{ route('admin.rentals.show', $booking) }}">
                                    @if ($session['status'] === \App\Domain\Rental\Enums\BookingStatus::Confirmed)
                                        Giao đồ
                                    @else
                                        Xác nhận trả
                                    @endif
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Không có lịch cần xử lý.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($lowSaleRows->isNotEmpty() || $emptyRentalRows->isNotEmpty())
        <div class="vp-split">
            @if ($lowSaleRows->isNotEmpty())
                <div>
                    <div class="admin-card">
                        <div class="p-3 fw-semibold">SKU bán tồn thấp</div>
                        <table class="table admin-table mb-0">
                            <thead><tr><th>Mã SKU</th><th>Sản phẩm</th><th>Còn</th></tr></thead>
                            <tbody>
                            @foreach ($lowSaleRows as $stock)
                                <tr>
                                    <td>{{ $stock->variant?->sku }}</td>
                                    <td>{{ $stock->variant?->product?->name }}</td>
                                    <td>{{ $stock->quantity_on_hand }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            @if ($emptyRentalRows->isNotEmpty())
                <div>
                    <div class="admin-card">
                        <div class="p-3 fw-semibold">SKU thuê hết món sẵn sàng</div>
                        <table class="table admin-table mb-0">
                            <thead><tr><th>Mã SKU</th><th>Sản phẩm</th></tr></thead>
                            <tbody>
                            @foreach ($emptyRentalRows as $variant)
                                <tr>
                                    <td>{{ $variant->sku }}</td>
                                    <td>{{ $variant->product?->name }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif
    <script type="application/json" id="dashboard-charts-config">{!! json_encode([
        'labels' => $revenueLabels,
        'values' => $revenueValues,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection
