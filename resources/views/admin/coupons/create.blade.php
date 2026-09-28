@extends('layouts.admin')
@section('title', 'Thêm mã giảm giá')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <h2>Thêm mã giảm giá</h2>
        <a class="btn btn-outline-secondary" href="{{ route('admin.coupons.index') }}"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
    <form method="post" action="{{ route('admin.coupons.store') }}" class="admin-card p-4" style="max-width: 720px">
        @csrf
        @include('admin.coupons._form')
    </form>
@endsection
