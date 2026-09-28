@extends('layouts.admin')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Sửa sản phẩm</h2>
            <p class="text-muted mb-0">{{ $product->name }}</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Quay lại</a>
    </div>
    <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('admin.products._form')
    </form>
@endsection
