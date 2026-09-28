@extends('layouts.admin')
@section('title', 'Danh mục')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Danh mục</h1>
            <p class="page-subtitle mb-0">Dụng cụ, quần áo, giày, ...</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-admin-primary"><i class="fas fa-plus me-1"></i> Thêm danh mục</a>
    </div>
    <div class="admin-card">
        <table class="table admin-table align-middle">
            <thead><tr><th>Tên</th><th>Slug</th><th>Sản phẩm</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->name }}</td>
                    <td class="text-muted">{{ $category->slug }}</td>
                    <td>{{ $category->products_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.edit', $category) }}"><i class="fas fa-pen"></i></a>
                        <form class="d-inline" method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Xóa danh mục này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">Chưa có danh mục.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
