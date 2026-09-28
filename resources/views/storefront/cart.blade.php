@extends('storefront.layouts.app')

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
    <h1 class="h3 mb-3">{{ $cartTitle }}</h1>

    @if ($lines === [])
        <div class="alert alert-light border">Giỏ trống. <a href="{{ route('catalog.index') }}">Chọn sản phẩm</a>.</div>
    @else
        <div class="table-responsive table-card">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Dòng</th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line['line_type'] === 'sale' ? 'Mua' : 'Thuê' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $line['product_name'] }}</div>
                                <div class="small text-secondary">{{ $line['sku'] }}
                                    @if ($line['size']) · size {{ $line['size'] }} @endif
                                    @if ($line['color']) · {{ $line['color'] }} @endif
                                </div>
                                @if ($line['line_type'] === 'rental')
                                    <div class="small">{{ $line['rental_start'] }} → {{ $line['rental_end'] }}</div>
                                @endif
                                @unless ($line['available'])
                                    <div class="text-danger small">Dòng này hiện không đặt được.</div>
                                @endunless
                            </td>
                            <td>{{ $line['quantity'] }}</td>
                            <td>
                                {{ number_format($line['line_total'], 0, ',', '.') }}đ
                                @if ($line['deposit_amount'] > 0)
                                    <div class="small">Cọc {{ number_format($line['deposit_amount'], 0, ',', '.') }}đ</div>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('cart.remove', $line['id']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="policy-card mt-3 col-lg-5 ms-lg-auto">
            @if ($merchandise_total > 0)
                <div>Hàng bán: <strong>{{ number_format($merchandise_total, 0, ',', '.') }}đ</strong></div>
            @endif
            @if ($rental_total > 0)
                <div>Tiền thuê: <strong>{{ number_format($rental_total, 0, ',', '.') }}đ</strong></div>
            @endif
            @if ($deposit_total > 0)
                <div>Cọc: <strong>{{ number_format($deposit_total, 0, ',', '.') }}đ</strong></div>
            @endif
            <hr>
            <div class="fs-5">Tạm tính: <strong>{{ number_format($grand_total, 0, ',', '.') }}đ</strong></div>
            <a class="btn btn-success mt-3" href="{{ route('checkout.show') }}">Thanh toán</a>
        </div>
    @endif
@endsection
