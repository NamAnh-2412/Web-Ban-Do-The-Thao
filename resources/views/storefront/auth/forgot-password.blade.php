@extends('storefront.layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
    <div class="policy-card col-lg-5 mx-auto">
        <h1 class="h4 mb-1">Quên mật khẩu</h1>
        <p class="text-muted mb-4">Nhập email tài khoản. Nếu email tồn tại, hệ thống gửi liên kết đặt lại mật khẩu.</p>

        <form method="post" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-4">
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
            <button class="btn btn-success w-100" type="submit">Gửi liên kết</button>
        </form>
        <p class="text-center text-muted mt-4 mb-0">
            <a href="{{ route('login') }}" class="fw-semibold">Quay lại đăng nhập</a>
        </p>
    </div>
@endsection
