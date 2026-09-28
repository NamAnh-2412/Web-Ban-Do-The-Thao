@extends('layouts.pos')
@section('title', 'Bán tại quầy')
@section('content')
@php
    $emptyCart = $lines === [];
    $customerId = old('customer_id', $ticketCustomer['customer_id'] ?? '');
@endphp
<div class="pos-desk">
    <header class="pos-topbar">
        <a class="pos-back" href="{{ route('admin.orders.index') }}"><i class="fas fa-arrow-left me-1"></i>Về danh sách đơn</a>
        <h1 class="pos-title"><span class="pos-title-icon"><i class="fas fa-plus"></i></span>Tạo đơn hàng</h1>
        <form method="post" action="{{ route('admin.pos.tickets.store') }}">
            @csrf
            @include('admin.pos._desk_query')
            <button class="pos-add-ticket" type="submit">+ Thêm đơn hàng</button>
        </form>
    </header>

    <div class="pos-tabs">
        @foreach ($tickets as $ticket)
            @php $ticketCount = count($ticket['lines'] ?? []); @endphp
            <div class="pos-tab-wrap {{ (string) $ticket['id'] === (string) $activeTicketId ? 'active' : '' }}">
                <form method="post" action="{{ route('admin.pos.tickets.switch', $ticket['id']) }}">
                    @csrf
                    @include('admin.pos._desk_query')
                    <button class="pos-tab" type="submit">
                        <div class="pos-tab-name"><i class="fas fa-clipboard-list"></i>{{ $ticket['label'] ?? 'Đơn hàng' }}</div>
                        <div class="pos-tab-meta">{{ $ticketCount === 0 ? 'Chưa có sản phẩm' : $ticketCount.' sản phẩm' }}</div>
                    </button>
                </form>
                @if (count($tickets) > 1)
                    <form class="pos-tab-remove" method="post" action="{{ route('admin.pos.tickets.destroy', $ticket['id']) }}" onsubmit="return confirm('Xóa đơn hàng này trên quầy?')">
                        @csrf
                        @method('DELETE')
                        @include('admin.pos._desk_query')
                        <button type="submit" title="Xóa đơn hàng" aria-label="Xóa đơn hàng">&times;</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    @if (session('status') || session('success'))
        <div class="alert alert-success pos-flash py-2 mb-0">{{ session('status') ?: session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-warning pos-flash py-2 mb-0">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger pos-flash py-2 mb-0">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="pos-main">
        <aside class="pos-col">
            <div class="pos-col-head"><span><i class="fas fa-list"></i>Nhóm sản phẩm</span></div>
            <div class="pos-cat-list">
                <a class="pos-cat {{ $categoryId === null ? 'active' : '' }}" href="{{ route('admin.pos.index', array_filter(['q' => $q !== '' ? $q : null])) }}">Tất cả</a>
                @foreach ($categories as $category)
                    <a class="pos-cat {{ (int) $categoryId === (int) $category->id ? 'active' : '' }}"
                       href="{{ route('admin.pos.index', array_filter(['category_id' => $category->id, 'q' => $q !== '' ? $q : null])) }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </aside>

        <section class="pos-col">
            <div class="pos-col-head">
                <span>Sản phẩm</span>
                <form method="get" action="{{ route('admin.pos.index') }}">
                    @if ($categoryId)
                        <input type="hidden" name="category_id" value="{{ $categoryId }}">
                    @endif
                    <input class="pos-search" type="search" name="q" value="{{ $q }}" placeholder="Tìm sản phẩm...">
                </form>
            </div>
            <div class="pos-products">
                @forelse ($products as $product)
                    @php
                        $saleVariants = $product->variants->filter(fn ($variant) => $variant->sale_price !== null);
                        $rentalVariants = $product->variants->filter(fn ($variant) => $variant->rental_price_per_day !== null);
                        $price = $saleVariants->first()?->sale_price ?? $rentalVariants->first()?->rental_price_per_day;
                    @endphp
                    <article class="pos-product">
                        @if ($saleVariants->count() === 1)
                            <form method="post" action="{{ route('admin.pos.add') }}">
                                @csrf
                                @include('admin.pos._desk_query')
                                <input type="hidden" name="line_type" value="sale">
                                <input type="hidden" name="product_variant_id" value="{{ $saleVariants->first()->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button class="pos-add-all" type="submit">
                                    <div class="pos-product-thumb">
                                        @if ($product->imageSrc())
                                            <img src="{{ $product->imageSrc() }}" alt="{{ $product->name }}" onerror="this.remove()">
                                        @else
                                            <i class="fas fa-box-open"></i>
                                        @endif
                                    </div>
                                    <div class="pos-product-body">
                                        <h3 class="pos-product-name">{{ $product->name }}</h3>
                                        @if ($price)
                                            <div class="pos-product-price">{{ number_format($price, 0, ',', '.') }}đ</div>
                                        @endif
                                    </div>
                                </button>
                            </form>
                        @else
                            <div class="pos-product-thumb">
                                @if ($product->imageSrc())
                                    <img src="{{ $product->imageSrc() }}" alt="{{ $product->name }}" onerror="this.remove()">
                                @else
                                    <i class="fas fa-box-open"></i>
                                @endif
                            </div>
                            <div class="pos-product-body">
                                <h3 class="pos-product-name">{{ $product->name }}</h3>
                                @if ($saleVariants->isNotEmpty() && $saleVariants->first()->sale_price)
                                    <div class="pos-product-price">{{ number_format($saleVariants->min('sale_price'), 0, ',', '.') }}đ</div>
                                @elseif ($price)
                                    <div class="pos-product-price">{{ number_format($price, 0, ',', '.') }}đ/ngày</div>
                                @endif
                                @if ($saleVariants->count() > 1)
                                    <div class="pos-product-actions">
                                        @foreach ($saleVariants as $variant)
                                            <form method="post" action="{{ route('admin.pos.add') }}">
                                                @csrf
                                                @include('admin.pos._desk_query')
                                                <input type="hidden" name="line_type" value="sale">
                                                <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button class="pos-chip" type="submit">
                                                    {{ $variant->size ?: $variant->color ?: $variant->sku }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if ($rentalVariants->isNotEmpty())
                            <div class="pos-product-body pt-0">
                                <button class="pos-chip" type="button" data-bs-toggle="collapse" data-bs-target="#rent-{{ $product->id }}">Thuê</button>
                                <div class="collapse mt-2" id="rent-{{ $product->id }}">
                                    @foreach ($rentalVariants as $variant)
                                        <form class="mb-2" method="post" action="{{ route('admin.pos.add') }}">
                                            @csrf
                                            @include('admin.pos._desk_query')
                                            <input type="hidden" name="line_type" value="rental">
                                            <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">
                                            <div class="small text-muted mb-1">{{ $variant->size ?: $variant->sku }} · {{ number_format($variant->rental_price_per_day, 0, ',', '.') }}đ/ngày</div>
                                            <input class="form-control form-control-sm mb-1" type="date" name="rental_start" min="{{ date('Y-m-d') }}" required>
                                            <input class="form-control form-control-sm mb-1" type="date" name="rental_end" min="{{ date('Y-m-d') }}" required>
                                            <label class="small text-muted mb-1 d-block">Số lượng</label>
                                            <input class="form-control form-control-sm mb-1 pos-rent-qty" type="number" name="quantity" min="1" value="1" required>
                                            <button class="pos-chip" type="submit">Thêm thuê</button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="pos-empty-catalog">Chưa có sản phẩm. Thêm ở mục Sản phẩm trước.</div>
                @endforelse
            </div>
        </section>

        <aside class="pos-col">
            <form id="pos-checkout" method="post" action="{{ route('admin.pos.store') }}" class="d-flex flex-column h-100">
                @csrf
                @include('admin.pos._desk_query')
                <input type="hidden" name="customer_mode" id="pos-customer-mode" value="{{ old('customer_mode', $customerId ? 'existing' : 'walkin') }}">
                <input type="hidden" name="customer_id" id="pos-customer-id" value="{{ $customerId }}">
                <input type="hidden" name="action" id="pos-checkout-action" value="save">
                <input type="hidden" name="payment_method" id="pos-payment-method" value="{{ old('payment_method', 'cash') }}">

                <div class="pos-col-head">
                    <span><i class="fas fa-user"></i>Thông tin khách</span>
                    <button class="pos-find" type="button" data-bs-toggle="modal" data-bs-target="#posCustomerModal">
                        <i class="fas fa-search me-1"></i>Tìm khách
                    </button>
                </div>
                <div class="pos-customer-fields">
                    <input id="pos-customer-name" name="walkin_name" value="{{ old('walkin_name', $ticketCustomer['customer_name'] ?? '') }}" placeholder="Tên khách (mặc định Khách lẻ)" autocomplete="off">
                    <input id="pos-customer-phone" name="walkin_phone" value="{{ old('walkin_phone', $ticketCustomer['customer_phone'] ?? '') }}" placeholder="SĐT" autocomplete="off">
                </div>

                <div class="pos-cart">
                    @forelse ($lines as $line)
                        <div class="pos-line">
                            <div>
                                <div class="pos-line-name">{{ $line['product_name'] }}</div>
                                <div class="pos-line-meta">
                                    {{ $line['line_type'] === 'sale' ? 'Mua' : 'Thuê' }}
                                    @if ($line['size']) · {{ $line['size'] }} @endif
                                    @if ($line['color']) · {{ $line['color'] }} @endif
                                    @if ($line['line_type'] === 'rental' && $line['rental_start'])
                                        · {{ $line['rental_start'] }} → {{ $line['rental_end'] }}
                                    @endif
                                </div>
                                <div class="pos-qty">
                                    <button type="submit" form="pos-qty-{{ $line['id'] }}-down">−</button>
                                    <span>{{ $line['quantity'] }}</span>
                                    <button type="submit" form="pos-qty-{{ $line['id'] }}-up">+</button>
                                </div>
                            </div>
                            <div>
                                <div class="pos-line-total">{{ number_format($line['line_total'], 0, ',', '.') }}đ</div>
                                <button class="btn btn-sm btn-link text-danger p-0" type="submit" form="pos-remove-{{ $line['id'] }}">Xóa</button>
                            </div>
                        </div>
                    @empty
                        <div class="pos-cart-empty">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Giỏ hàng trống</span>
                        </div>
                    @endforelse
                </div>

                <div class="pos-footer">
                    <div class="pos-breakdown">
                        <div><span>Hàng</span><span>{{ number_format($merchandise_total, 0, ',', '.') }}đ</span></div>
                        <div><span>Thuê</span><span>{{ number_format($rental_total, 0, ',', '.') }}đ</span></div>
                        <div><span>Cọc</span><span>{{ number_format($deposit_total, 0, ',', '.') }}đ</span></div>
                    </div>
                    <div class="pos-total">
                        <span>Tổng tiền:</span>
                        <strong>{{ number_format($grand_total, 0, ',', '.') }}đ</strong>
                    </div>
                    @error('payment_method')
                        <div class="small text-danger mb-2">{{ $message }}</div>
                    @enderror
                    <div class="pos-actions">
                        <button class="pos-btn-save" type="submit" id="pos-btn-save" @disabled($emptyCart)>
                            <i class="fas fa-save me-1"></i>Lưu đơn
                        </button>
                        <button class="pos-btn-pay" type="button" id="pos-open-pay" @disabled($emptyCart)>Thanh toán</button>
                    </div>
                </div>
            </form>
        </aside>
    </div>
</div>

@foreach ($lines as $line)
    <form id="pos-qty-{{ $line['id'] }}-down" method="post" action="{{ route('admin.pos.qty', $line['id']) }}">
        @csrf
        @include('admin.pos._desk_query')
        <input type="hidden" name="delta" value="-1">
    </form>
    <form id="pos-qty-{{ $line['id'] }}-up" method="post" action="{{ route('admin.pos.qty', $line['id']) }}">
        @csrf
        @include('admin.pos._desk_query')
        <input type="hidden" name="delta" value="1">
    </form>
    <form id="pos-remove-{{ $line['id'] }}" method="post" action="{{ route('admin.pos.remove', $line['id']) }}">
        @csrf
        @method('DELETE')
        @include('admin.pos._desk_query')
    </form>
@endforeach

<div class="modal fade" id="posCustomerModal" tabindex="-1" aria-labelledby="posCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="posCustomerModalLabel">Tìm khách</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <input class="form-control mb-3" id="pos-customer-filter" type="search" placeholder="Tên, SĐT hoặc email...">
                @forelse ($customers as $customer)
                    <button class="pos-customer-row w-100 bg-transparent border-0 text-start"
                            type="button"
                            data-pick-customer
                            data-id="{{ $customer->id }}"
                            data-name="{{ $customer->name }}"
                            data-phone="{{ $customer->phone }}"
                            data-search="{{ strtolower($customer->name.' '.$customer->phone.' '.$customer->email) }}">
                        <span>
                            <strong>{{ $customer->name }}</strong>
                            <div class="small text-muted">{{ $customer->phone ?: $customer->email }}</div>
                        </span>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                @empty
                    <p class="text-muted mb-0">Chưa có khách đăng ký. Để trống tên sẽ lưu là Khách lẻ.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@include('admin.pos._pay_modal')
@endsection
