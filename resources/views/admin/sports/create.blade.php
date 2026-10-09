@extends('layouts.admin')
@section('title', 'Thêm môn thể thao')
@section('content')
    <h1 class="page-title mb-4">Thêm môn thể thao</h1>
    <form method="post" action="{{ route('admin.sports.store') }}" class="admin-card p-4" style="max-width:640px" enctype="multipart/form-data">
        @csrf
        @include('admin.sports._form')
        <button class="btn btn-success" type="submit">Lưu</button>
        <a href="{{ route('admin.sports.index') }}" class="btn btn-secondary">Hủy</a>
    </form>
@endsection
