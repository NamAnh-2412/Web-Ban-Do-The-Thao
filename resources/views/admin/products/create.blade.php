@extends('layouts.admin')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Thêm sản phẩm mới</h2>
            <p class="text-muted mb-0">Nhập thông tin chung và các phân loại (size, màu, giá bán/thuê).</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Quay lại</a>
    </div>
    <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.products._form')
    </form>
@endsection
