@extends('layouts.admin')
@section('title', 'Đơn #'.$order->id)
@section('content')
    @php
        $canRefund = in_array($order->status, [
            \App\Domain\Order\Enums\OrderStatus::Paid,
            \App\Domain\Order\Enums\OrderStatus::Processing,
            \App\Domain\Order\Enums\OrderStatus::Completed,
        ], true) && ! $order->canBeCancelled();
        $cancelConfirm = $order->status->canCancelAfterPayment()
            ? 'Hủy đơn sẽ hoàn đủ tiền đã thu và cộng lại kho. Tiếp tục?'
            : 'Hủy đơn?';
        $canCompleteSale = $order->isSaleOnly() && in_array($order->status, [
            \App\Domain\Order\Enums\OrderStatus::Paid,
            \App\Domain\Order\Enums\OrderStatus::Processing,
        ], true);
    @endphp
    <p class="small mb-3"><a href="{{ route('admin.orders.index') }}">← Đơn hàng</a></p>
    <h1 class="page-title">Đơn #{{ $order->id }}</h1>
    <p class="page-subtitle mb-4">{{ $order->status->label() }} · {{ $order->channel->label() }}
        @if ($order->shipping_method) · {{ $order->shipping_method->label() }} @endif
    </p>
    <p>{{ $order->user?->name }} · {{ $order->shipping_name }} · {{ $order->shipping_phone }}<br>{{ $order->shipping_address }}</p>
    @if ($order->ghn_order_code || $order->fulfillment_status)
        <p class="mb-3">Giao: <strong>{{ $order->fulfillment_status?->label() }}</strong>
            @if ($order->ghn_order_code) · mã GHN <code>{{ $order->ghn_order_code }}</code> @endif
            @if ((float) $order->shipping_fee > 0) · ship {{ number_format($order->shipping_fee, 0, ',', '.') }}đ @endif
        </p>
    @endif
    <div class="admin-card p-3 mb-3">
        @foreach ($order->items as $item)
            <div class="mb-2">{{ $item->line_type->value === 'sale' ? 'Mua' : 'Thuê' }} — {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format($item->line_total, 0, ',', '.') }}đ</div>
        @endforeach
        <strong>Tổng {{ number_format($order->grand_total, 0, ',', '.') }}đ</strong>
    </div>

    @if ($order->bookings->isNotEmpty())
        <div class="admin-card mb-3">
            <div class="p-3 fw-semibold">Lịch thuê</div>
            <table class="table admin-table mb-0">
                <thead><tr><th>#</th><th>Món</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                @foreach ($order->bookings as $booking)
                    <tr>
                        <td>#{{ $booking->id }}</td>
                        <td>{{ $booking->variant?->product?->name ?? $booking->variant?->sku }}</td>
                        <td>{{ $booking->start_date->toDateString() }} → {{ $booking->end_date->toDateString() }}</td>
                        <td>{{ $booking->status->label() }}</td>
                        <td><a href="{{ route('admin.rentals.show', $booking) }}">Xem lịch</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="admin-card mb-4">
        <div class="p-3 fw-semibold">Thanh toán</div>
        <table class="table admin-table mb-0">
            <thead><tr><th>Loại</th><th>Số tiền</th><th>Trạng thái</th><th>Hình thức</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->kind->label() }}</td>
                    <td>{{ number_format($payment->amount, 0, ',', '.') }}đ</td>
                    <td>{{ $payment->status->label() }}</td>
                    <td>{{ $payment->method->label() }}</td>
                    <td>
                        @if ($payment->status->value === 'pending')
                            <form method="post" action="{{ route('admin.payments.confirm', $payment) }}">@csrf<button class="btn btn-sm btn-success">Xác nhận nhận tiền</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Chưa có khoản thanh toán.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($order->status === \App\Domain\Order\Enums\OrderStatus::Pending && $order->channel === \App\Domain\Order\Enums\OrderChannel::Online)
        <form class="d-inline" method="post" action="{{ route('admin.orders.confirm', $order) }}">@csrf<button class="btn btn-success">Chốt đơn</button></form>
    @endif
    @if (in_array($order->status, [\App\Domain\Order\Enums\OrderStatus::Pending, \App\Domain\Order\Enums\OrderStatus::Confirmed], true))
        <form class="d-inline" method="post" action="{{ route('admin.orders.mark-paid', $order) }}">@csrf<button class="btn btn-primary">Xác nhận thanh toán</button></form>
    @endif
    @if ($order->canBeCancelled())
        <form class="d-inline" method="post" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm(@json($cancelConfirm))">@csrf<button class="btn btn-outline-danger">Hủy đơn</button></form>
    @endif
    @if ($canCompleteSale)
        <form class="d-inline" method="post" action="{{ route('admin.orders.complete', $order) }}">@csrf<button class="btn btn-outline-success">Hoàn tất</button></form>
    @endif
    @if ($order->isDelivery() && $order->ghn_order_code)
        <form class="d-inline" method="post" action="{{ route('admin.orders.fulfillment', $order) }}">
            @csrf
            <input type="hidden" name="fulfillment_status" value="delivering">
            <button class="btn btn-outline-primary btn-sm">Đang giao</button>
        </form>
        <form class="d-inline" method="post" action="{{ route('admin.orders.fulfillment', $order) }}">
            @csrf
            <input type="hidden" name="fulfillment_status" value="delivered">
            <button class="btn btn-outline-primary btn-sm">Đã giao</button>
        </form>
    @endif

    @if ($canRefund)
        <div class="admin-card p-4 mt-4" style="max-width:520px">
            <h2 class="h5">Hoàn tiền</h2>
            <p class="small text-muted">Lập khoản hoàn cho đơn #{{ $order->id }}. Không hoàn vượt số đã thu.</p>
            <form method="post" action="{{ route('admin.payments.refund') }}">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                <div class="mb-2">
                    <label class="form-label" for="amount">Số tiền</label>
                    <input class="form-control" id="amount" name="amount" type="number" min="1" step="1000" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="note">Ghi chú</label>
                    <input class="form-control" id="note" name="note" required>
                </div>
                <button class="btn btn-outline-danger">Hoàn</button>
            </form>
        </div>
    @endif
@endsection
