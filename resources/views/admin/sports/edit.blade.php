@extends('layouts.admin')
@section('title', 'Sửa môn thể thao')
@section('content')
    <h1 class="page-title mb-4">Sửa môn thể thao</h1>
    <form method="post" action="{{ route('admin.sports.update', $sport) }}" class="admin-card p-4" style="max-width:640px" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.sports._form', ['sport' => $sport])
        <button class="btn btn-success" type="submit">Cập nhật</button>
        <a href="{{ route('admin.sports.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
@endsection
