@forelse ($orders as $order)
    @php
        $kind = $order->customerKindLabel();
        $kindClass = match ($kind) {
            'Hỗn hợp' => 'is-mixed',
            'Thuê' => 'is-rent',
            default => 'is-sale',
        };
    @endphp
    <article class="order-card mb-3">
        <div class="order-card-head">
            <div class="order-card-title-wrap">
                <h2 class="order-card-title">Đơn hàng #{{ $order->id }}</h2>
                <span class="order-kind {{ $kindClass }}">{{ $kind }}</span>
                <span class="order-channel">{{ $order->channel->label() }}</span>
            </div>
            <div class="order-card-meta">
                <span class="order-card-date">{{ $order->created_at?->format('d/m/Y H:i') }}</span>
                <span class="order-status">• {{ $order->status->label() }}</span>
            </div>
        </div>

        <ul class="order-card-items">
            @foreach ($order->items as $item)
                <li class="order-card-item">
                    <div class="order-card-thumb">
                        @if ($item->imageSrc())
                            <img src="{{ $item->imageSrc() }}" alt="" width="56" height="56">
                        @else
                            <span class="order-card-thumb-fallback" aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="order-card-item-main">
                        <div class="order-card-item-name">{{ $item->product_name }}</div>
                        <div class="order-card-item-sku">
                            {{ $item->sku }}
                            @if ($item->size) · Size {{ $item->size }} @endif
                            @if ($item->color) · {{ $item->color }} @endif
                        </div>
                        <span class="order-line-badge {{ $item->line_type->value === 'sale' ? 'is-sale' : 'is-rent' }}">
                            {{ $item->line_type->value === 'sale' ? 'Mua' : 'Thuê' }}
                        </span>
                    </div>
                    <div class="order-card-item-side">
                        <div class="order-card-qty">Số lượng: {{ $item->quantity }}</div>
                        <div class="order-card-price">{{ number_format($item->line_total, 0, ',', '.') }}đ</div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="order-card-foot">
            <div class="order-card-breakdown">
                @if ((float) $order->merchandise_total > 0)
                    <span>Hàng mua: {{ number_format($order->merchandise_total, 0, ',', '.') }}đ</span>
                @endif
                @if ((float) $order->rental_total > 0)
                    <span>Thuê: {{ number_format($order->rental_total, 0, ',', '.') }}đ</span>
                @endif
                @if ((float) $order->deposit_total > 0)
                    <span>Cọc: {{ number_format($order->deposit_total, 0, ',', '.') }}đ</span>
                @endif
            </div>
            <div class="order-card-total-wrap">
                <div class="order-card-total">
                    <span>Tổng thanh toán:</span>
                    <strong>{{ number_format($order->grand_total, 0, ',', '.') }}đ</strong>
                </div>
                <a class="btn btn-outline-success order-card-link" href="{{ route('orders.show', $order->id) }}">Xem chi tiết →</a>
            </div>
        </div>
    </article>
@empty
    <div class="alert alert-light border">Chưa có đơn mua. <a href="{{ route('catalog.index', ['offer_mode' => 'sale']) }}">Chọn sản phẩm</a>.</div>
@endforelse

{{ $orders->links('pagination::bootstrap-5') }}
