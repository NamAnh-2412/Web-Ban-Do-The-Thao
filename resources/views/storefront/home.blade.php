@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/catalog.css'])
@include('partials.js', ['file' => 'js/storefront/sport-carousel.js'])

@section('title', 'Trang chủ')

@section('content')
@php
    $sportCollections = $sports->filter(fn ($sport) => $sport->imageSrc());
@endphp
    <section class="hero mb-4" aria-label="WebTheThao">
        <img
            class="hero-banner"
            src="{{ $bannerUrl }}"
            alt="WebTheThao — Đồ thể thao sẵn sàng ra sân"
            width="1600"
            height="550"
        >
        <div class="hero-cta-row">
            <a class="hero-cta hero-cta--buy" href="{{ route('catalog.index', ['offer_mode' => 'sale']) }}">Mua ngay</a>
            <a class="hero-cta hero-cta--rent" href="{{ route('catalog.index', ['offer_mode' => 'rental']) }}">Thuê đồ</a>
        </div>
    </section>

    <section class="offer-strip mb-4" aria-label="Mua, thuê và cọc">
        <a class="offer-card" href="{{ route('catalog.index', ['offer_mode' => 'sale']) }}">
            <span class="offer-card-head">
                <span class="offer-card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.6 13.4 12.7 21.3a2 2 0 0 1-2.8 0L2.7 14.1a2 2 0 0 1 0-2.8l7.9-7.9a2 2 0 0 1 1.4-.6H20a1 1 0 0 1 1 1v7.9a2 2 0 0 1-.4 1.3z"/>
                        <circle cx="16.5" cy="7.5" r="1.2" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
                <strong>Mua</strong>
            </span>
            <span class="offer-card-text">Đồ mới 100%, bảo hành chính hãng</span>
        </a>
        <a class="offer-card" href="{{ route('catalog.index', ['offer_mode' => 'rental']) }}">
            <span class="offer-card-head">
                <span class="offer-card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="5" width="17" height="15" rx="2"/>
                        <path d="M8 3.5v3M16 3.5v3M3.5 10h17"/>
                        <path d="m9.5 14.5 1.8 1.8 3.7-3.8"/>
                    </svg>
                </span>
                <strong>Thuê</strong>
            </span>
            <span class="offer-card-text">Giá chỉ từ 30.000đ/ngày, đa dạng môn</span>
        </a>
        <a class="offer-card" href="{{ route('policies.deposit') }}">
            <span class="offer-card-head">
                <span class="offer-card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="6.5" rx="7" ry="2.5"/>
                        <path d="M5 6.5v4c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-4"/>
                        <path d="M5 10.5v4c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-4"/>
                        <path d="M5 14.5v3c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-3"/>
                    </svg>
                </span>
                <strong>Cọc</strong>
            </span>
            <span class="offer-card-text">Hoàn cọc 100% ngay khi trả đồ</span>
        </a>
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

    @if ($sportCollections->isNotEmpty())
        <section class="sport-carousel mb-4" data-sport-carousel aria-roledescription="carousel" aria-label="Bộ sưu tập theo môn">
            <div class="section-head d-flex justify-content-between align-items-end mb-3">
                <h2 class="h4 mb-0">Bộ sưu tập</h2>
                <div class="sport-carousel-controls">
                    <button type="button" class="sport-carousel-btn" data-sport-carousel-prev aria-label="Môn trước">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                    </button>
                    <button type="button" class="sport-carousel-btn" data-sport-carousel-next aria-label="Môn sau">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
            <div class="sport-carousel-viewport">
                <div class="sport-carousel-track" data-sport-carousel-track>
                    @foreach ($sportCollections as $index => $sport)
                        <article class="sport-slide" data-sport-carousel-slide @if ($index === 0) aria-current="true" @endif>
                            <div class="sport-slide-media">
                                <img src="{{ $sport->imageSrc() }}" alt="{{ $sport->displayHeadline() }}" width="1200" height="700" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                            </div>
                            <div class="sport-slide-body">
                                <h3 class="sport-slide-title">{{ $sport->displayHeadline() }}</h3>
                                <p class="sport-slide-text">{{ $sport->displayDescription() }}</p>
                                <div class="sport-slide-actions">
                                    <a class="btn btn-light" href="{{ route('catalog.index', ['sport_id' => $sport->id]) }}">Xem sản phẩm</a>
                                    <a class="btn btn-outline-light" href="{{ route('policies.rental') }}">Chính sách thuê</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
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
