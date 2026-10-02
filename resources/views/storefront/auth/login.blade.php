@extends('storefront.layouts.app')

@section('title', 'Đăng nhập')

@include('partials.css', ['file' => 'css/shared/password-toggle.css'])

@section('content')
    <div class="policy-card col-lg-5 mx-auto">
        <h1 class="h4 mb-1">Đăng nhập</h1>
        <p class="text-muted mb-3">Hai loại tài khoản, không dùng chung vai trò:</p>
        <ul class="small text-muted mb-4">
            <li><strong>Khách</strong> — mua, thuê, giỏ hàng, đơn của mình. Đăng ký bên dưới nếu chưa có.</li>
            <li><strong>Cửa hàng</strong> (nhân viên / quản trị, giống máy POS) — kho, đơn, thanh toán ở khu Quản trị. Do quản trị cấp, không tự đăng ký.</li>
        </ul>

        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input
                    class="form-control @error('email') is-invalid @enderror"
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                @include('storefront.partials.password-field', [
                    'id' => 'password',
                    'name' => 'password',
                    'autocomplete' => 'current-password',
                ])
            </div>
            <div class="d-flex justify-content-end mb-3">
                <a class="small" href="{{ route('password.request') }}">Quên mật khẩu?</a>
            </div>
            <div class="form-check mb-4">
                <input type="checkbox" id="remember" name="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Ghi nhớ đăng nhập</label>
            </div>
            <button class="btn btn-success w-100" type="submit">Đăng nhập</button>
        </form>
        @include('partials.js', ['file' => 'js/storefront/password-toggle.js'])
        <p class="text-center text-muted mt-4 mb-0">
            Chưa có tài khoản? <a href="{{ route('register') }}" class="fw-semibold">Đăng ký ngay</a>
        </p>
    </div>
@endsection
