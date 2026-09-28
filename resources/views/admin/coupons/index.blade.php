@extends('layouts.admin')
@section('title', 'Mã giảm giá')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Mã giảm giá</h1>
            <p class="page-subtitle mb-0">Giảm trên tiền hàng / thuê, không trừ tiền cọc.</p>
        </div>
        <a href="{{ route('admin.coupons.create') }}" class="btn btn-admin-primary"><i class="fas fa-plus me-1"></i> Thêm mã</a>
    </div>
    <div class="admin-card">
        <table class="table admin-table align-middle">
            <thead><tr><th>Mã</th><th>Tên</th><th>Giảm</th><th>Áp dụng</th><th>Lượt</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            @forelse ($coupons as $coupon)
                <tr>
                    <td class="fw-semibold">{{ $coupon->code }}</td>
                    <td>{{ $coupon->name }}</td>
                    <td>
                        @if ($coupon->discount_type->value === 'percent')
                            {{ rtrim(rtrim(number_format($coupon->discount_value, 2, ',', '.'), '0'), ',') }}%
                        @else
                            {{ number_format($coupon->discount_value, 0, ',', '.') }}đ
                        @endif
                    </td>
                    <td>{{ $coupon->applies_to->label() }}</td>
                    <td>{{ $coupon->used_count }}@if ($coupon->max_uses) / {{ $coupon->max_uses }} @endif</td>
                    <td>{{ $coupon->is_active ? 'Bật' : 'Tắt' }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.coupons.edit', $coupon) }}"><i class="fas fa-pen"></i></a>
                        <form class="d-inline" method="post" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Xóa mã này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có mã giảm giá.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
