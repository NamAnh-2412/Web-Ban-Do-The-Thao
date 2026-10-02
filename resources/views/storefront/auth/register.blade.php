@extends('storefront.layouts.app')

@section('title', 'Đăng ký')

@include('partials.css', ['file' => 'css/shared/password-toggle.css'])

@section('content')
    <div class="policy-card col-lg-5 mx-auto">
        <h1 class="h4 mb-1">Đăng ký tài khoản khách</h1>
        <p class="text-muted mb-4">Chỉ tạo tài khoản mua / thuê. Tài khoản cửa hàng (nhân viên, quản trị) do quản trị cấp, không đăng ký tại đây.</p>

        <form method="post" action="{{ route('register.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="name">Họ và tên</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Điện thoại</label>
                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                @include('storefront.partials.password-field', [
                    'id' => 'password',
                    'name' => 'password',
                    'autocomplete' => 'new-password',
                    'minlength' => 8,
                ])
            </div>
            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Xác nhận mật khẩu</label>
                @include('storefront.partials.password-field', [
                    'id' => 'password_confirmation',
                    'name' => 'password_confirmation',
                    'autocomplete' => 'new-password',
                ])
            </div>
            <button class="btn btn-success w-100" type="submit">Đăng ký</button>
        </form>
        @include('partials.js', ['file' => 'js/storefront/password-toggle.js'])
        <p class="text-center text-muted mt-4 mb-0">
            Đã có tài khoản? <a href="{{ route('login') }}" class="fw-semibold">Đăng nhập</a>
        </p>
    </div>
@endsection
