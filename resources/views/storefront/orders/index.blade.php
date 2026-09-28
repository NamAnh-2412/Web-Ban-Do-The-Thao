@extends('storefront.layouts.app')

@section('title', 'Đơn hàng')

@section('content')
    <h1 class="h3 mb-3">Đơn của tôi</h1>
    @forelse ($orders as $order)
        <a class="d-block policy-card mb-3 text-decoration-none text-dark" href="{{ route('orders.show', $order->id) }}">
            <div class="d-flex justify-content-between">
                <strong>Đơn #{{ $order->id }}</strong>
                <span>{{ $order->status->label() }} · {{ $order->channel->label() }}</span>
            </div>
            <div class="small text-secondary mb-1">
                <span class="badge text-bg-light">{{ $order->customerKindLabel() }}</span>
                {{ $order->created_at?->format('d/m/Y H:i') }}
            </div>
            <div>{{ number_format($order->grand_total, 0, ',', '.') }}đ
                @php
                    $parts = [];
                    if ((float) $order->merchandise_total > 0) {
                        $parts[] = 'hàng '.number_format($order->merchandise_total, 0, ',', '.').'đ';
                    }
                    if ((float) $order->rental_total > 0) {
                        $parts[] = 'thuê '.number_format($order->rental_total, 0, ',', '.').'đ';
                    }
                    if ((float) $order->deposit_total > 0) {
                        $parts[] = 'cọc '.number_format($order->deposit_total, 0, ',', '.').'đ';
                    }
                @endphp
                @if ($parts !== [])
                    ({{ implode(' + ', $parts) }})
                @endif
            </div>
        </a>
    @empty
        <div class="alert alert-light border">Chưa có đơn.</div>
    @endforelse
    {{ $orders->links('pagination::bootstrap-5') }}
@endsection
