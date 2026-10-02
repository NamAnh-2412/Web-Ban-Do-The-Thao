@extends('storefront.layouts.app')

@section('title', 'Đặt lại mật khẩu')

@include('partials.css', ['file' => 'css/shared/password-toggle.css'])

@section('content')
    <div class="policy-card col-lg-5 mx-auto">
        <h1 class="h4 mb-1">Đặt lại mật khẩu</h1>
        <p class="text-muted mb-4">Chọn mật khẩu mới, ít nhất 8 ký tự.</p>

        <form method="post" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input
                    class="form-control @error('email') is-invalid @enderror"
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu mới</label>
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
                    'minlength' => 8,
                ])
            </div>
            <button class="btn btn-success w-100" type="submit">Lưu mật khẩu</button>
        </form>
        @include('partials.js', ['file' => 'js/storefront/password-toggle.js'])
    </div>
@endsection
