@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/checkout.css'])
@include('partials.js', ['file' => 'js/storefront/checkout-ghn.js'])

@section('title', 'Thanh toán')

@section('content')
    <h1 class="h3 mb-1">Thanh toán</h1>
    <p class="text-secondary">Tồn kho sẽ khóa khi đặt. Tiền mua/thuê và cọc là hai khoản riêng. Phí ship (nếu giao nhà) tính trên server, không ăn coupon.</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="policy-card">
                <h2 class="h5">Giao hàng / nhận đồ</h2>
                <form method="post" action="{{ route('checkout.store') }}" id="checkout-form">
                    @csrf
                    <div class="mb-3">
                        <div class="form-label">Hình thức nhận</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="shipping_method" id="ship-pickup" value="pickup" @checked(old('shipping_method', 'pickup') === 'pickup')>
                            <label class="form-check-label" for="ship-pickup">Nhận tại quầy (không GHN)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="shipping_method" id="ship-delivery" value="delivery" @checked(old('shipping_method') === 'delivery')>
                            <label class="form-check-label" for="ship-delivery">Giao nhà (GHN)</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="shipping_name">Họ tên <span class="text-danger">*</span></label>
                        <input class="form-control @error('shipping_name') is-invalid @enderror" id="shipping_name" name="shipping_name" value="{{ old('shipping_name', auth()->user()->name) }}" required>
                        @error('shipping_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="shipping_phone">Điện thoại <span class="text-danger">*</span></label>
                        <input class="form-control @error('shipping_phone') is-invalid @enderror" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone', auth()->user()->phone) }}" required>
                        @error('shipping_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div id="ghn-fields" class="d-none">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="to_province_id">Tỉnh</label>
                                <select class="form-select" id="to_province_id" name="to_province_id"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="to_district_id">Quận</label>
                                <select class="form-select" id="to_district_id" name="to_district_id"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="to_ward_code">Phường</label>
                                <select class="form-select" id="to_ward_code" name="to_ward_code"></select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="shipping_address">Địa chỉ <span class="text-danger">*</span></label>
                        <input class="form-control @error('shipping_address') is-invalid @enderror" id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" required>
                        @error('shipping_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Ghi chú</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="coupon_code">Mã giảm giá</label>
                        <input class="form-control @error('coupon_code') is-invalid @enderror" id="coupon_code" name="coupon_code" value="{{ old('coupon_code', $coupon_code ?? '') }}" placeholder="Ví dụ SALE10">
                        @error('coupon_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if (!empty($coupon_error))
                            <div class="form-text text-danger">{{ $coupon_error }}</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <div class="form-label">Hình thức thanh toán</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay-bank" value="bank_transfer" @checked(old('payment_method', 'bank_transfer') === 'bank_transfer')>
                            <label class="form-check-label" for="pay-bank">Chuyển khoản QR</label>
                        </div>
                        <p class="small text-secondary mb-2">
                            {{ $bank['bank_name'] ?? config('payments.bank.name') }}
                            — {{ $bank['account_name'] ?? config('payments.bank.holder') }}.
                            Sau khi đặt, trang đơn hiện QR để quét (MoMo hoặc app ngân hàng VietQR).
                            Cửa hàng xác nhận khi nhận tiền.
                        </p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay-momo" value="momo" @checked(old('payment_method') === 'momo')>
                            <label class="form-check-label" for="pay-momo">MoMo (thẻ test, tự chốt đơn)</label>
                        </div>
                        <div class="form-check d-none" id="pay-cod-wrap">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay-cod" value="cod" @checked(old('payment_method') === 'cod')>
                            <label class="form-check-label" for="pay-cod">COD giao nhà (cọc gộp vào thu hộ GHN)</label>
                        </div>
                    </div>
                    <button class="btn btn-success" type="submit">Đặt hàng</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="policy-card">
                <h2 class="h5">Đơn</h2>
                <ul class="list-unstyled">
                    @foreach ($lines as $line)
                        <li class="mb-2">
                            <span class="badge text-bg-light">{{ $line['line_type'] === 'sale' ? 'Mua' : 'Thuê' }}</span>
                            {{ $line['product_name'] }} × {{ $line['quantity'] }}
                            @if ($line['line_type'] === 'rental')
                                <div class="small">{{ $line['rental_start'] }} → {{ $line['rental_end'] }}
                                    @if ($line['deposit_amount'] > 0)
                                        · cọc {{ number_format($line['deposit_amount'], 0, ',', '.') }}đ
                                    @endif
                                </div>
                            @endif
                            <div class="small">{{ number_format($line['line_total'], 0, ',', '.') }}đ</div>
                        </li>
                    @endforeach
                </ul>
                @if ($merchandise_total > 0)
                    <div>Hàng: {{ number_format($merchandise_total, 0, ',', '.') }}đ</div>
                @endif
                @if ($rental_total > 0)
                    <div>Thuê: {{ number_format($rental_total, 0, ',', '.') }}đ</div>
                @endif
                @if ($deposit_total > 0)
                    <div>Cọc: {{ number_format($deposit_total, 0, ',', '.') }}đ</div>
                @endif
                @if (($discount_total ?? 0) > 0)
                    <div class="text-success">Giảm: −{{ number_format($discount_total, 0, ',', '.') }}đ</div>
                @endif
                <div id="ship-fee-row" class="d-none">Ship: <span id="ship-fee-value">0</span>đ</div>
                <div class="fw-bold mt-2">Tổng: <span id="grand-total">{{ number_format($grand_total, 0, ',', '.') }}</span>đ</div>
            </div>
        </div>
    </div>
    <script type="application/json" id="checkout-ghn-config">{!! json_encode([
        'baseGrand' => (float) $grand_total,
        'itemCount' => max(1, count($lines)),
        'feeUrl' => route('shipping.fee'),
        'provincesUrl' => route('shipping.provinces'),
        'districtsUrl' => route('shipping.districts'),
        'wardsUrl' => route('shipping.wards'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection
