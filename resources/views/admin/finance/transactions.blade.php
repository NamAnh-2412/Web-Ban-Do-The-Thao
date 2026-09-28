@extends('layouts.admin')
@section('title', 'Giao dịch thanh toán')
@section('content')
@php
    $badge = [
        'paid' => 'success',
        'pending' => 'warning',
        'failed' => 'danger',
        'cancelled' => 'secondary',
        'refunded' => 'info',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="page-title">Giao dịch thanh toán</h1>
        <p class="page-subtitle mb-0">Chỉ cập nhật thủ công đơn COD. MoMo / CK giữ theo cổng hoặc sổ khoản.</p>
    </div>
    <a href="{{ route('admin.finance.export', request()->query()) }}" class="btn btn-outline-secondary">Xuất CSV</a>
</div>

<nav class="nav nav-pills mb-4">
    <a class="nav-link" href="{{ route('admin.finance.index', request()->query()) }}">Thống kê</a>
    <a class="nav-link active" href="{{ route('admin.finance.transactions', request()->query()) }}">Giao dịch thanh toán</a>
</nav>

@error('payment_status')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

@include('admin.finance._filters', ['action' => route('admin.finance.transactions'), 'showSort' => true])

<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Người nhận</th>
                    <th>Điện thoại</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Cổng</th>
                    <th>Thanh toán</th>
                    <th>Ngày tạo</th>
                    <th>Ngày thu</th>
                    <th>Cập nhật COD</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $isCod = $order->gateway === 'cod';
                        $allowed = $codTransitions[$order->payment_status] ?? [];
                    @endphp
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order->id) }}">#{{ $order->id }}</a></td>
                        <td>{{ $order->name }}</td>
                        <td>{{ $order->phone }}</td>
                        <td class="text-end">{{ number_format((float) $order->total_price, 0, ',', '.') }} đ</td>
                        <td>{{ $methods[$order->gateway] ?? $order->gateway }}</td>
                        <td>
                            <span class="badge text-bg-{{ $badge[$order->payment_status] ?? 'secondary' }}">
                                {{ $statuses[$order->payment_status] ?? $order->payment_status }}
                            </span>
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $order->paid_at ? \Illuminate\Support\Carbon::parse($order->paid_at)->format('d/m/Y H:i') : '—' }}</td>
                        <td>
                            @if ($isCod && count($allowed) > 0)
                                <form method="POST" action="{{ route('admin.finance.update-status', $order->id) }}" class="d-flex gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="current_payment_status" value="{{ $order->payment_status }}">
                                    <input type="hidden" name="current_order_status" value="{{ $order->status }}">
                                    <input type="hidden" name="current_payment_id" value="{{ $order->payment_id ?? 0 }}">
                                    <select name="payment_status" class="form-select form-select-sm" style="min-width: 9rem">
                                        @foreach ($allowed as $status)
                                            <option value="{{ $status }}" @selected($status === $order->payment_status)>
                                                {{ $statuses[$status] ?? $status }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-admin-primary">Lưu</button>
                                </form>
                            @else
                                <span class="text-muted small">Không chỉnh tay</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có giao dịch khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        <div class="p-3">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
