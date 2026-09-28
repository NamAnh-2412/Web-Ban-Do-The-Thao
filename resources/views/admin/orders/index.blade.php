@extends('layouts.admin')
@section('title', 'Đơn hàng')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-title">Đơn hàng</h1>
            <p class="page-subtitle mb-0">
                @if ($listDate)
                    Đơn tạo ngày {{ \Illuminate\Support\Carbon::parse($listDate)->format('d/m/Y') }} — quầy, online, mua, thuê, đã hủy.
                @else
                    Đơn online chờ nhận (mọi ngày).
                @endif
            </p>
        </div>
        @if ($pendingCount > 0)
            <a class="btn btn-warning" href="{{ route('admin.orders.index', ['status' => 'pending', 'channel' => 'online', 'date' => 'all']) }}">
                Đơn online chờ nhận: {{ $pendingCount }}
            </a>
        @endif
    </div>
    <form class="row g-2 mb-3 align-items-end" method="get">
        <div class="col-auto">
            <label class="form-label mb-1" for="order-date">Ngày</label>
            <input id="order-date" class="form-control" type="date" name="date" value="{{ $listDate }}" max="{{ now()->toDateString() }}">
        </div>
        <div class="col-auto">
            <label class="form-label mb-1" for="order-channel">Kênh</label>
            <select id="order-channel" name="channel" class="form-select">
                <option value="">Mọi kênh</option>
                <option value="online" @selected(request('channel') === 'online')>Online</option>
                <option value="pos" @selected(request('channel') === 'pos')>Tại quầy</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-1" for="order-status">Trạng thái</label>
            <select id="order-status" name="status" class="form-select">
                <option value="">Mọi trạng thái</option>
                @foreach (\App\Domain\Order\Enums\OrderStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Xem</button></div>
        @if ($listDate !== now()->toDateString())
            <div class="col-auto"><a class="btn btn-outline-primary" href="{{ route('admin.orders.index') }}">Hôm nay</a></div>
        @endif
    </form>
    <div class="admin-card">
        <table class="table admin-table">
            <thead><tr><th>Mã đơn</th><th>Khách</th><th>Kênh</th><th>Trạng thái</th><th>Tổng</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr class="{{ $order->status === \App\Domain\Order\Enums\OrderStatus::Pending ? 'table-warning' : '' }}">
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->user?->name }}</td>
                    <td>{{ $order->channel->label() }}</td>
                    <td>{{ $order->status->label() }}</td>
                    <td>{{ number_format($order->grand_total, 0, ',', '.') }}đ</td>
                    <td><a href="{{ route('admin.orders.show', $order) }}">Xem</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        @if ($listDate)
                            Không có đơn ngày {{ \Illuminate\Support\Carbon::parse($listDate)->format('d/m/Y') }}.
                        @else
                            Không có đơn.
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
@endsection
