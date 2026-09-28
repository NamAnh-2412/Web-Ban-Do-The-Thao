@extends('layouts.admin')
@section('title', 'Thêm danh mục')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <h2>Thêm danh mục mới</h2>
        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('admin.categories.store') }}" class="admin-card p-4" style="max-width: 520px">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Tên danh mục <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <button class="btn btn-success"><i class="fas fa-save"></i> Lưu</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
@endsection
