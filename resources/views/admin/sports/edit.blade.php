@extends('layouts.admin')
@section('content')
    <h2 class="mb-4">Sửa môn thể thao</h2>
    <form method="post" action="{{ route('admin.sports.update', $sport) }}" class="admin-card p-4" style="max-width:520px">
        @csrf @method('PUT')
        <div class="mb-3">
            <label class="form-label" for="name">Tên môn</label>
            <input class="form-control" id="name" name="name" value="{{ old('name', $sport->name) }}" required>
        </div>
        <button class="btn btn-success">Cập nhật</button>
    </form>
@endsection
