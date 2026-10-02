@extends('layouts.admin')
@include('partials.css', ['file' => 'css/shared/password-toggle.css'])
@include('partials.js', ['file' => 'js/storefront/password-toggle.js'])
@section('title', 'Thêm tài khoản cửa hàng')
@section('content')
    <div class="d-flex justify-content-between mb-4">
        <h2>Thêm tài khoản cửa hàng</h2>
        <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
    <form method="post" action="{{ route('admin.users.store') }}" class="admin-card p-4" style="max-width: 640px">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <p class="text-muted">Tài khoản này vào khu Quản trị để bán hàng. Khách tự đăng ký trên website.</p>
        <div class="mb-3">
            <label class="form-label" for="name">Họ và tên <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="phone">Điện thoại</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="role">Vai trò cửa hàng <span class="text-danger">*</span></label>
            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                <option value="staff" @selected(old('role', 'staff') === 'staff')>Nhân viên — kho, đơn, thanh toán</option>
                <option value="admin" @selected(old('role') === 'admin')>Quản trị — thêm được tài khoản cửa hàng khác</option>
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Mật khẩu <span class="text-danger">*</span></label>
            @include('storefront.partials.password-field', [
                'id' => 'password',
                'name' => 'password',
                'autocomplete' => 'new-password',
                'minlength' => 8,
            ])
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Xác nhận mật khẩu <span class="text-danger">*</span></label>
            @include('storefront.partials.password-field', [
                'id' => 'password_confirmation',
                'name' => 'password_confirmation',
                'autocomplete' => 'new-password',
            ])
        </div>
        <button class="btn btn-admin-primary" type="submit">Tạo tài khoản</button>
    </form>
@endsection
