@extends('layouts.admin')
@section('title', 'Sản phẩm')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <div>
            <h1 class="page-title">Quản lý sản phẩm</h1>
            <p class="page-subtitle mb-0">Danh sách sản phẩm, giá bán / thuê và phân loại</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-admin-primary"><i class="fas fa-plus me-2"></i>Thêm sản phẩm mới</a>
    </div>
    <div class="admin-card">
        <table class="table table-hover admin-table align-middle">
            <thead>
                <tr>
                    <th>Hình</th><th>Tên</th><th>Danh mục</th><th>Chế độ</th><th>Phân loại</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>
                        @if ($product->imageSrc())
                            <img src="{{ $product->imageSrc() }}" class="product-thumbnail" alt="">
                        @else
                            <div class="product-thumbnail-empty"><i class="far fa-image"></i></div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ $product->name }}</td>
                    <td><span class="badge text-bg-light">{{ $product->category?->name }}</span></td>
                    <td>{{ $product->offer_mode->label() }}</td>
                    <td>
                        @foreach ($product->variants->take(3) as $variant)
                            <span class="variant-chip">{{ $variant->color }} / {{ $variant->size }} (kho {{ $variant->stock?->quantity_on_hand ?? 0 }})</span>
                        @endforeach
                    </td>
                    <td class="text-nowrap text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.products.edit', $product) }}"><i class="fas fa-pen"></i></a>
                        <form class="d-inline" method="post" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Xóa sản phẩm này?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có sản phẩm.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
