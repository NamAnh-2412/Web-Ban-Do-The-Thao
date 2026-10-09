@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/cart.css'])
@include('partials.js', ['file' => 'js/storefront/cart.js'])

@section('title', 'Giỏ hàng')

@php
    $cartTitle = match ($cart_kind ?? 'empty') {
        'rental' => 'Giỏ thuê',
        'mixed' => 'Giỏ mua + thuê',
        'sale' => 'Giỏ mua',
        default => 'Giỏ hàng',
    };
@endphp

@section('content')
    <div class="cart-head mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $cartTitle }}</h1>
            @if ($lines !== [])
                <p class="text-secondary mb-0">Kiểm tra sản phẩm trước khi thanh toán.</p>
            @endif
        </div>
        <a class="btn btn-outline-secondary cart-continue" href="{{ route('catalog.index') }}">← Tiếp tục mua sắm</a>
    </div>

    @if ($lines === [])
        <div class="alert alert-light border">Giỏ trống. <a href="{{ route('catalog.index') }}">Chọn sản phẩm</a>.</div>
    @else
        <div class="cart-layout">
            <div class="cart-panel">
                <div class="cart-table-head d-none d-md-grid">
                    <span>Sản phẩm</span>
                    <span>Trạng thái</span>
                    <span>Số lượng</span>
                    <span>Số tiền</span>
                    <span></span>
                </div>

                @foreach ($lines as $line)
                    <article class="cart-row @unless($line['available']) cart-row--unavailable @endunless">
                        <div class="cart-product">
                            <div class="cart-thumb">
                                @if ($line['image_src'])
                                    <img src="{{ $line['image_src'] }}" alt="{{ $line['product_name'] }}" width="72" height="72">
                                @else
                                    <span class="cart-thumb-fallback" aria-hidden="true"></span>
                                @endif
                            </div>
                            <div class="cart-product-meta">
                                <div class="cart-product-name">{{ $line['product_name'] }}</div>
                                <div class="cart-product-sku">
                                    {{ $line['sku'] }}
                                    @if ($line['size']) · Size {{ $line['size'] }} @endif
                                    @if ($line['color']) · {{ $line['color'] }} @endif
                                </div>
                                @if ($line['line_type'] === 'rental' && $line['rental_start'] && $line['rental_end'])
                                    <div class="cart-rental-dates">
                                        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 10h17"/></svg>
                                        {{ \Illuminate\Support\Carbon::parse($line['rental_start'])->format('d/m/Y') }}
                                        –
                                        {{ \Illuminate\Support\Carbon::parse($line['rental_end'])->format('d/m/Y') }}
                                        @if ($line['rental_days'])
                                            ({{ $line['rental_days'] }} ngày)
                                        @endif
                                    </div>
                                @endif
                                @unless ($line['available'])
                                    <div class="text-danger small mt-1">Dòng này hiện không đặt được.</div>
                                @endunless
                            </div>
                        </div>

                        <div class="cart-status">
                            @if ($line['line_type'] === 'sale')
                                <span class="cart-badge cart-badge--sale">Mua</span>
                            @else
                                <span class="cart-badge cart-badge--rent">Thuê</span>
                            @endif
                        </div>

                        <div class="cart-qty">
                            <form method="post" action="{{ route('cart.quantity', $line['id']) }}" class="cart-qty-form" data-cart-qty>
                                @csrf
                                @method('PATCH')
                                <button class="cart-qty-btn" type="submit" name="quantity" value="{{ max(1, $line['quantity'] - 1) }}" aria-label="Giảm số lượng" @disabled($line['quantity'] <= 1)>−</button>
                                <span class="cart-qty-value" aria-live="polite">{{ $line['quantity'] }}</span>
                                <button class="cart-qty-btn" type="submit" name="quantity" value="{{ min(99, $line['quantity'] + 1) }}" aria-label="Tăng số lượng">+</button>
                            </form>
                        </div>

                        <div class="cart-price">
                            <strong>{{ number_format($line['line_total'], 0, ',', '.') }}đ</strong>
                            @if ($line['deposit_amount'] > 0)
                                <div class="cart-deposit">
                                    + Cọc: {{ number_format($line['deposit_amount'], 0, ',', '.') }}đ
                                    <span class="cart-deposit-tag">Hoàn lại</span>
                                </div>
                            @endif
                        </div>

                        <div class="cart-actions">
                            <button
                                class="cart-action-btn cart-action-btn--detail"
                                type="button"
                                data-cart-detail-open
                                data-line-id="{{ $line['id'] }}"
                                aria-haspopup="dialog"
                            >Chi tiết</button>
                            <form method="post" action="{{ route('cart.remove', $line['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button class="cart-action-btn cart-action-btn--remove" type="submit" aria-label="Xóa khỏi giỏ">×</button>
                            </form>
                        </div>
                    </article>

                    <div class="cart-modal" id="cart-detail-{{ $line['id'] }}" hidden data-cart-modal>
                        <div class="cart-modal-backdrop" data-cart-detail-close tabindex="-1"></div>
                        <div class="cart-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cart-detail-title-{{ $line['id'] }}">
                            <div class="cart-modal-head">
                                <h2 class="h5 mb-0" id="cart-detail-title-{{ $line['id'] }}">Chi tiết dòng giỏ</h2>
                                <button type="button" class="cart-modal-close" data-cart-detail-close aria-label="Đóng">×</button>
                            </div>
                            <div class="cart-modal-body">
                                <div class="cart-modal-product">
                                    <div class="cart-thumb cart-thumb--lg">
                                        @if ($line['image_src'])
                                            <img src="{{ $line['image_src'] }}" alt="" width="96" height="96">
                                        @else
                                            <span class="cart-thumb-fallback" aria-hidden="true"></span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $line['product_name'] }}</div>
                                        <div class="small text-secondary mt-1">
                                            @if ($line['line_type'] === 'sale') Mua mới @else Thuê đồ @endif
                                            · {{ number_format($line['line_total'], 0, ',', '.') }}đ
                                        </div>
                                        @if ($line['product_slug'])
                                            <a class="small d-inline-block mt-2" href="{{ route('catalog.show', $line['product_slug']) }}">Xem trang sản phẩm</a>
                                        @endif
                                    </div>
                                </div>

                                @if (count($line['variants']) > 0)
                                    <form method="post" action="{{ route('cart.variant', $line['id']) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="form-label" for="variant-{{ $line['id'] }}">Đổi size / màu</label>
                                        <select class="form-select" id="variant-{{ $line['id'] }}" name="product_variant_id" required>
                                            @foreach ($line['variants'] as $variant)
                                                <option value="{{ $variant['id'] }}" @selected((int) $variant['id'] === (int) $line['product_variant_id'])>
                                                    {{ $variant['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-success mt-3" type="submit">Lưu biến thể</button>
                                    </form>
                                @else
                                    <p class="text-secondary small mt-3 mb-0">Không còn biến thể khác để đổi.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="cart-summary policy-card">
                <h2 class="h5 mb-3">Tóm tắt đơn hàng</h2>
                @if ($merchandise_total > 0)
                    <div class="cart-summary-row">
                        <span>Tiền hàng mua</span>
                        <strong>{{ number_format($merchandise_total, 0, ',', '.') }}đ</strong>
                    </div>
                @endif
                @if ($rental_total > 0)
                    <div class="cart-summary-row">
                        <span>Tiền thuê dụng cụ</span>
                        <strong>{{ number_format($rental_total, 0, ',', '.') }}đ</strong>
                    </div>
                @endif
                @if ($deposit_total > 0)
                    <div class="cart-summary-row">
                        <span>Tiền cọc giữ đồ</span>
                        <strong>+{{ number_format($deposit_total, 0, ',', '.') }}đ</strong>
                    </div>
                    <p class="cart-summary-note">Sẽ hoàn trả 100% khi nhận lại đồ.</p>
                @endif
                <hr>
                <div class="cart-summary-total">
                    <span>Tổng tạm tính</span>
                    <strong>{{ number_format($grand_total, 0, ',', '.') }}đ</strong>
                </div>
                <p class="small text-secondary mt-2 mb-3">Phí giao hàng tính ở bước thanh toán.</p>
                <a class="btn btn-success w-100" href="{{ route('checkout.show') }}">Tiến hành thanh toán →</a>
                <a class="cart-summary-link" href="{{ route('catalog.index') }}">← Chọn thêm sản phẩm khác</a>
            </aside>
        </div>
    @endif
@endsection
