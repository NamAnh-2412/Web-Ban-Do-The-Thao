@extends('layouts.admin')
@section('content')
    <h2 class="mb-4">Thêm môn thể thao</h2>
    <form method="post" action="{{ route('admin.sports.store') }}" class="admin-card p-4" style="max-width:520px">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="name">Tên môn <span class="text-danger">*</span></label>
            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <button class="btn btn-success">Lưu</button>
        <a href="{{ route('admin.sports.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
@endsection
