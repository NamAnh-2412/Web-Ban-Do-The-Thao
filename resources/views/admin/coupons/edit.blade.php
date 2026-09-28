@extends('layouts.admin')
@section('title', 'Sửa mã giảm giá')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <h2>Sửa mã {{ $coupon->code }}</h2>
        <a class="btn btn-outline-secondary" href="{{ route('admin.coupons.index') }}"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
    <form method="post" action="{{ route('admin.coupons.update', $coupon) }}" class="admin-card p-4" style="max-width: 720px">
        @csrf
        @method('PUT')
        @include('admin.coupons._form')
    </form>
@endsection
