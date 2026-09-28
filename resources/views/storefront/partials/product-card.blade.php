@php
    $modeLabel = match ($product->offer_mode->value) {
        'sale' => 'Chỉ bán',
        'rental' => 'Chỉ thuê',
        default => 'Bán & thuê',
    };
    $saleFrom = $product->variants->whereNotNull('sale_price')->min('sale_price');
    $rentFrom = $product->variants->whereNotNull('rental_price_per_day')->min('rental_price_per_day');
    $focus = $offerFocus ?? null;
    $showSale = $saleFrom && $focus !== 'rental';
    $showRent = $rentFrom && $focus !== 'sale';
    $showParams = ['slug' => $product->slug];
    if (in_array($focus, ['sale', 'rental'], true)) {
        $showParams['offer_mode'] = $focus;
    }
@endphp
<a class="card card-product" href="{{ route('catalog.show', $showParams) }}">
    <div class="thumb">
        @if ($product->imageSrc())
            <img src="{{ $product->imageSrc() }}" alt="{{ $product->name }}" onerror="this.remove()">
        @else
            {{ $product->sport->name ?? 'Thể thao' }}
        @endif
    </div>
    <div class="card-body">
        <span class="badge-offer">{{ $modeLabel }}</span>
        <div class="small text-secondary mb-1">{{ $product->category->name ?? '' }}</div>
        <h3 class="h6 product-name">{{ $product->name }}</h3>
        @if ($product->isSaleSoldOut())
            <div class="small text-danger mb-1">Hết hàng bán</div>
        @endif
        @if ($showSale)
            <div class="price">Mua từ {{ number_format($saleFrom, 0, ',', '.') }}đ</div>
        @endif
        @if ($showRent)
            <div class="{{ $focus === 'rental' || ! $showSale ? 'price' : 'small' }}">Thuê từ {{ number_format($rentFrom, 0, ',', '.') }}đ/ngày</div>
        @endif
    </div>
</a>
