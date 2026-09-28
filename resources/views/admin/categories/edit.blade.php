@extends('layouts.admin')
@section('title', 'Sửa danh mục')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <h2>Sửa danh mục</h2>
        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Quay lại</a>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('admin.categories.update', $category) }}" class="admin-card p-4" style="max-width: 520px">
        @csrf @method('PUT')
        <div class="mb-3">
            <label class="form-label" for="name">Tên danh mục <span class="text-danger">*</span></label>
            <input class="form-control" id="name" name="name" value="{{ old('name', $category->name) }}" required>
        </div>
        <button class="btn btn-success">Cập nhật</button>
    </form>
@endsection
