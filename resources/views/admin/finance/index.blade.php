@extends('layouts.admin')
@include('partials.css', ['file' => 'css/admin/finance.css'])
@include('partials.js', ['file' => 'vendor/chartjs/chart.umd.min.js'])
@include('partials.js', ['file' => 'js/admin/finance-charts.js'])
@section('title', 'Tài chính')
@section('content')
@php
    $paidAmount = (float) ($summary->paid_amount ?? 0);
    $pendingAmount = (float) ($summary->pending_amount ?? 0);
    $totalAmount = (float) ($summary->total_amount ?? 0);
    $orderCount = (int) ($summary->order_count ?? 0);
    $statusChartLabels = collect($statuses)->values();
    $statusChartData = collect($statuses)->keys()->map(fn ($key) => (float) ($statusTotals->get($key)?->total_amount ?? 0))->values();
    $methodChartLabels = collect($methods)->values();
    $methodChartData = collect($methods)->keys()->map(fn ($key) => (float) ($methodTotals->get($key)?->paid_amount ?? 0))->values();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="page-title">Tài chính</h1>
        <p class="page-subtitle mb-0">Mỗi đơn một giao dịch đại diện, ưu tiên khoản đã thu.</p>
    </div>
    <a href="{{ route('admin.finance.export', request()->query()) }}" class="btn btn-outline-secondary">Xuất CSV</a>
</div>

<nav class="nav nav-pills mb-4">
    <a class="nav-link active" href="{{ route('admin.finance.index', request()->query()) }}">Thống kê</a>
    <a class="nav-link" href="{{ route('admin.finance.transactions', request()->query()) }}">Giao dịch thanh toán</a>
</nav>

@include('admin.finance._filters', ['action' => route('admin.finance.index')])

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-4 h-100">
            <div class="text-muted small">Số đơn (theo bộ lọc)</div>
            <div class="fs-3 fw-bold">{{ number_format($orderCount) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-4 h-100">
            <div class="text-muted small">Tổng giá trị đơn</div>
            <div class="fs-3 fw-bold">{{ number_format($totalAmount, 0, ',', '.') }} đ</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-4 h-100">
            <div class="text-muted small">Đã thanh toán</div>
            <div class="fs-3 fw-bold">{{ number_format($paidAmount, 0, ',', '.') }} đ</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-4 h-100">
            <div class="text-muted small">Chờ thanh toán</div>
            <div class="fs-3 fw-bold">{{ number_format($pendingAmount, 0, ',', '.') }} đ</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="p-3 border-bottom fw-semibold">Theo trạng thái thanh toán</div>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Trạng thái</th>
                            <th class="text-end">Số đơn</th>
                            <th class="text-end">Tổng tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $key => $label)
                            @php $row = $statusTotals->get($key); @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end">{{ number_format((int) ($row->order_count ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($row->total_amount ?? 0), 0, ',', '.') }} đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="p-3 border-bottom fw-semibold">Theo phương thức</div>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Cổng</th>
                            <th class="text-end">Số đơn</th>
                            <th class="text-end">Tổng tiền</th>
                            <th class="text-end">Đã thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($methods as $key => $label)
                            @php $row = $methodTotals->get($key); @endphp
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end">{{ number_format((int) ($row->order_count ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((float) ($row->total_amount ?? 0), 0, ',', '.') }} đ</td>
                                <td class="text-end">{{ number_format((float) ($row->paid_amount ?? 0), 0, ',', '.') }} đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="p-3 border-bottom">Biểu đồ theo trạng thái</div>
            <div class="p-3"><canvas id="financeStatusChart" height="260"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="p-3 border-bottom">Đã thu theo cổng</div>
            <div class="p-3"><canvas id="financeMethodChart" height="260"></canvas></div>
        </div>
    </div>
</div>
<script type="application/json" id="finance-charts-config">{!! json_encode([
    'statusLabels' => $statusChartLabels,
    'statusData' => $statusChartData,
    'methodLabels' => $methodChartLabels,
    'methodData' => $methodChartData,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection
