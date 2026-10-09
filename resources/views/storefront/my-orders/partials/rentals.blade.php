@forelse ($sessions as $session)
    @php
        $lead = $session['lead'];
        $extendable = $session['bookings']->filter(fn ($row) => in_array($row->status->value, ['confirmed', 'active'], true)
            && ! $row->extensions->contains(fn ($extension) => $extension->status->value === 'pending'));
    @endphp
    <article class="order-card mb-3">
        <div class="order-card-head">
            <div class="order-card-title-wrap">
                <h2 class="order-card-title">
                    Buổi thuê
                    @if ($session['item_count'] > 1)
                        ({{ $session['item_count'] }} món)
                    @endif
                </h2>
                <span class="order-kind is-rent">Thuê</span>
            </div>
            <div class="order-card-meta">
                <span class="order-card-date">
                    {{ $lead->start_date->format('d/m/Y') }} → {{ $lead->end_date->format('d/m/Y') }}
                </span>
                <span class="order-status">• {{ $session['status']->label() }}</span>
            </div>
        </div>

        <ul class="order-card-items">
            @foreach ($session['bookings'] as $row)
                <li class="order-card-item">
                    <div class="order-card-thumb">
                        @if ($row->variant?->product?->imageSrc())
                            <img src="{{ $row->variant->product->imageSrc() }}" alt="" width="56" height="56">
                        @else
                            <span class="order-card-thumb-fallback" aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="order-card-item-main">
                        <div class="order-card-item-name">{{ $row->variant?->product?->name ?? $row->variant?->sku ?? 'Món thuê' }}</div>
                        <div class="order-card-item-sku">
                            {{ $row->variant?->sku }}
                            @if ($row->variant?->size) · Size {{ $row->variant->size }} @endif
                            @if ($row->variant?->color) · {{ $row->variant->color }} @endif
                        </div>
                        <span class="order-line-badge is-rent">Thuê</span>
                    </div>
                    <div class="order-card-item-side">
                        <div class="order-card-price">{{ number_format($row->rental_amount, 0, ',', '.') }}đ</div>
                        @if ((float) $row->deposit_amount > 0)
                            <div class="order-card-qty">Cọc {{ number_format($row->deposit_amount, 0, ',', '.') }}đ</div>
                        @endif
                        <div class="order-card-qty">{{ $row->status->label() }}</div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="order-card-foot">
            <div class="order-card-breakdown">
                @if ($lead->order_id)
                    <span>Thuộc <a href="{{ route('orders.show', $lead->order_id) }}">đơn #{{ $lead->order_id }}</a></span>
                @endif
            </div>
            <div class="order-card-total-wrap">
                @if ($lead->order_id)
                    <a class="btn btn-outline-success order-card-link" href="{{ route('orders.show', $lead->order_id) }}">Xem đơn →</a>
                @endif
            </div>
        </div>

        @if ($extendable->isNotEmpty() && $lead->order_id)
            <div class="order-card-extend">
                @include('storefront.partials.rental-extension-form', [
                    'orderId' => $lead->order_id,
                    'lead' => $lead,
                ])
            </div>
        @endif
    </article>
@empty
    <div class="alert alert-light border">Chưa có lịch thuê đang mở. <a href="{{ route('catalog.index', ['offer_mode' => 'rental']) }}">Thuê dụng cụ</a>.</div>
@endforelse
