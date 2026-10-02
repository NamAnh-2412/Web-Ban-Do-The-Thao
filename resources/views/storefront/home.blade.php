@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/catalog.css'])

@section('title', 'Trang chủ')

@section('content')
    <section class="hero p-4 p-lg-5 mb-4">
        <div class="hero-orbs" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="hero-content">
            <h1 class="display-6 fw-bold">Đồ thể thao sẵn sàng ra sân</h1>
            <div class="hero-actions">
                <a class="btn btn-light" href="{{ route('catalog.index') }}">Xem sản phẩm</a>
                <a class="btn btn-outline-light" href="{{ route('policies.rental') }}">Chính sách thuê</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <strong>Mua</strong>
            </div>
            <div class="hero-stat">
                <strong>Thuê</strong>
            </div>
            <div class="hero-stat">
                <strong>Cọc</strong>
            </div>
        </div>
    </section>

    @if ($sports->isNotEmpty())
        <div class="sport-chips mb-4">
            @foreach ($sports as $sport)
                <a class="sport-chip" href="{{ route('catalog.index', ['sport_id' => $sport->id]) }}">
                    <span class="sport-chip-dot" aria-hidden="true"></span>
                    {{ $sport->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if (($bestSellers ?? collect())->isNotEmpty())
        <div class="section-head d-flex justify-content-between align-items-end mb-3">
            <h2 class="h4 mb-0">Bán chạy</h2>
            <a href="{{ route('catalog.index', ['offer_mode' => 'sale']) }}">Mua ngay</a>
        </div>
        <div class="vp-grid mb-4">
            @foreach ($bestSellers as $index => $product)
                <div class="reveal" style="--delay: {{ $index * 70 }}ms">
                    @include('storefront.partials.product-card')
                </div>
            @endforeach
        </div>
    @endif

    <div class="section-head d-flex justify-content-between align-items-end mb-3">
        <h2 class="h4 mb-0">Sản phẩm nổi bật</h2>
        <a href="{{ route('catalog.index') }}">Tất cả</a>
    </div>

    <div class="vp-grid">
        @forelse ($products as $index => $product)
            <div class="reveal" style="--delay: {{ $index * 70 }}ms">
                @include('storefront.partials.product-card')
            </div>
        @empty
            <div>
                <div class="alert alert-light border">Chưa có sản phẩm.</div>
            </div>
        @endforelse
    </div>
@endsection
