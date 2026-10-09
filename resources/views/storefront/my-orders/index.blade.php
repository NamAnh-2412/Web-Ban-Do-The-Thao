@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/my-orders.css'])

@section('title', 'Đơn của tôi')

@section('content')
    <header class="my-orders-head mb-4">
        <h1 class="my-orders-title">Đơn hàng của tôi</h1>
        <p class="my-orders-lead mb-0">Quản lý đơn mua sắm và theo dõi lịch nhận/trả dụng cụ thể thao đã thuê.</p>
    </header>

    <nav class="my-orders-tabs mb-4" aria-label="Kênh đơn của tôi">
        <a
            class="my-orders-tab {{ $tab === 'mua' ? 'is-active' : '' }}"
            href="{{ route('my-orders.index', ['tab' => 'mua']) }}"
        >
            <span class="my-orders-tab-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16l-1.2 12.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8L4 7z"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
            </span>
            <span>Mua</span>
            <span class="my-orders-tab-count">{{ $orderCount }}</span>
        </a>
        <a
            class="my-orders-tab {{ $tab === 'thue' ? 'is-active' : '' }}"
            href="{{ route('my-orders.index', ['tab' => 'thue']) }}"
        >
            <span class="my-orders-tab-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 1.5"/></svg>
            </span>
            <span>Thuê</span>
            <span class="my-orders-tab-count">{{ $rentalCount }}</span>
        </a>
    </nav>

    @if ($tab === 'mua')
        @include('storefront.my-orders.partials.purchases')
    @else
        @include('storefront.my-orders.partials.rentals')
    @endif
@endsection
