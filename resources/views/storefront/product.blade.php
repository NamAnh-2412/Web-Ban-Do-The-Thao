@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/product.css'])
@include('partials.js', ['file' => 'js/storefront/product.js'])

@section('title', $product->name)

@php
    $canSale = in_array($product->offer_mode->value, ['sale', 'both'], true);
    $canRent = in_array($product->offer_mode->value, ['rental', 'both'], true);
    $defaultMode = match (true) {
        ($requestedMode ?? null) === 'rental' && $canRent => 'rental',
        ($requestedMode ?? null) === 'sale' && $canSale => 'sale',
        $canSale => 'sale',
        default => 'rental',
    };
    $modeBadge = match ($product->offer_mode->value) {
        'sale' => 'Chỉ bán',
        'rental' => 'Chỉ thuê',
        default => 'Bán & thuê',
    };
    $payload = [
        'offer_mode' => $product->offer_mode->value,
        'variants' => $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'size' => $v->size,
            'color' => $v->color,
            'sale_price' => $v->sale_price,
            'rental_price_per_day' => $v->rental_price_per_day,
            'rental_price_per_week' => $v->rental_price_per_week,
            'deposit_amount' => $v->deposit_amount,
        ])->values(),
    ];
@endphp

@section('content')
    <nav class="small mb-3">
        <a href="{{ route('catalog.index') }}">Sản phẩm</a>
        @if ($product->category)
            / {{ $product->category->name }}
        @endif
    </nav>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card card-product gallery-card">
                <div class="thumb">
                    @if ($product->imageSrc())
                        <img src="{{ $product->imageSrc() }}" alt="{{ $product->name }}" onerror="this.style.display='none'">
                    @endif
                    <span>{{ $product->sport->name ?? 'Thể thao' }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <h1 class="h3">{{ $product->name }}</h1>
            <p class="mb-2"><span class="badge-offer">{{ $modeBadge }}</span></p>
            @if (($reviews ?? collect())->isNotEmpty())
                <p class="small mb-1">Đánh giá: <strong>{{ $reviewAvg }}</strong>/5 ({{ $reviews->count() }} lượt)</p>
            @endif
            <p class="text-secondary">{{ $product->description }}</p>

            <div class="mode-toggle btn-group mb-3" role="group" aria-label="Chọn mua hoặc thuê">
                @if ($canSale)
                    <button type="button" class="btn btn-outline-success {{ $defaultMode === 'sale' ? 'active' : '' }}" data-mode="sale" id="btn-mode-sale">Mua</button>
                @endif
                @if ($canRent)
                    <button type="button" class="btn btn-outline-success {{ $defaultMode === 'rental' ? 'active' : '' }}" data-mode="rental" id="btn-mode-rental">Thuê</button>
                @endif
            </div>

            <label class="form-label">Biến thể</label>
            <select class="form-select mb-3" id="variant-select">
                @foreach ($product->variants as $variant)
                    <option value="{{ $variant->id }}">
                        {{ $variant->sku }}
                        @if ($variant->size) · size {{ $variant->size }} @endif
                        @if ($variant->color) · {{ $variant->color }} @endif
                    </option>
                @endforeach
            </select>

            <div id="panel-sale" class="{{ $defaultMode === 'sale' ? '' : 'd-none' }}">
                <div class="price fs-4 mb-1" id="sale-price">—</div>
                <div class="small mb-3" id="sale-availability">Đang kiểm tra tồn kho bán…</div>
            </div>

            <div id="panel-rental" class="{{ $defaultMode === 'rental' ? '' : 'd-none' }}">
                <div class="price fs-4 mb-1" id="rent-price">—</div>
                <div class="small mb-2" id="rent-deposit"></div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label" for="start-date">Ngày nhận</label>
                        <input class="form-control" type="date" id="start-date">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="end-date">Ngày trả</label>
                        <input class="form-control" type="date" id="end-date">
                    </div>
                </div>
                <div class="small mb-3" id="rental-availability">Chọn ngày để xem món thuê còn trống.</div>
                <div class="small fw-semibold mb-3" id="rental-quote"></div>
            </div>

            @if (auth()->user()?->isStoreAccount())
                <div class="alert alert-warning mb-2">Tài khoản cửa hàng không mua trên website. Xử lý đơn ở khu Quản trị.</div>
                <a class="btn btn-success" href="{{ route('admin.dashboard') }}">Mở Quản trị</a>
            @else
                <form method="post" action="{{ route('cart.add') }}" id="add-cart-form">
                    @csrf
                    <input type="hidden" name="line_type" id="cart-line-type" value="{{ $defaultMode }}">
                    <input type="hidden" name="product_variant_id" id="cart-variant-id" value="{{ $product->variants->first()?->id }}">
                    <input type="hidden" name="rental_start" id="cart-start">
                    <input type="hidden" name="rental_end" id="cart-end">
                    <div class="mb-3" id="qty-wrap">
                        <label class="form-label" for="cart-qty" id="cart-qty-label">{{ $defaultMode === 'sale' ? 'Số lượng mua' : 'Số lượng thuê' }}</label>
                        <input class="form-control" style="max-width: 8rem;" type="number" name="quantity" id="cart-qty" value="1" min="1">
                        <div class="form-text" id="cart-qty-hint">{{ $defaultMode === 'rental' ? 'Mỗi món là một lịch thuê riêng.' : '' }}</div>
                    </div>
                    <button class="btn btn-success" type="submit" id="add-cart-btn">{{ $defaultMode === 'sale' ? 'Thêm mua' : 'Thêm thuê' }}</button>
                </form>
                <p class="small text-secondary mt-2 mb-0">Có thể mua và thuê cùng một giỏ. Thuê nhiều món: chọn số lượng — hệ thống gán từng món trống.</p>
            @endif
        </div>
    </div>

    <div class="policy-card mt-4">
        <h2 class="h5">Đánh giá</h2>
        @forelse ($reviews ?? [] as $review)
            <div class="border-bottom py-3">
                <div class="fw-semibold">{{ $review->user->name ?? 'Khách' }} · {{ $review->rating }}/5
                    <span class="badge text-bg-light">{{ $review->kind->value === 'sale' ? 'Mua' : 'Thuê' }}</span>
                </div>
                <p class="mb-0">{{ $review->comment }}</p>
            </div>
        @empty
            <p class="text-secondary mb-0">Chưa có đánh giá. Mua hoặc thuê rồi thanh toán để viết nhận xét.</p>
        @endforelse
    </div>
    <script type="application/json" id="product-page-config">{!! json_encode([
        'apiBase' => url('/'),
        'product' => $payload,
        'canSale' => $canSale,
        'canRent' => $canRent,
        'defaultMode' => $defaultMode,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection
