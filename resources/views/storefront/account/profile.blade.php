@extends('storefront.layouts.app')

@section('title', 'Hồ sơ')

@section('content')
    <div class="policy-card col-lg-6 mx-auto">
        <h1 class="h4 mb-1">Hồ sơ khách</h1>
        <p class="text-secondary mb-4">Sửa tên, số điện thoại, mật khẩu. Email đã xác thực không đổi tại đây.</p>

        <form method="post" action="{{ route('account.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="name">Họ tên</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" value="{{ $user->email }}" disabled>
                <div class="form-text">{{ $user->hasVerifiedEmail() ? 'Đã xác thực.' : 'Chưa xác thực.' }}</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Điện thoại</label>
                <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0xxxxxxxxx">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu mới (tuỳ chọn)</label>
                <input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password" minlength="8">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Xác nhận mật khẩu mới</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation">
            </div>
            <button class="btn btn-success" type="submit">Lưu hồ sơ</button>
            <a class="btn btn-outline-secondary ms-2" href="{{ route('rentals.schedule') }}">Lịch thuê</a>
            <a class="btn btn-outline-secondary ms-2" href="{{ route('messages.show') }}">Tin nhắn cửa hàng</a>
        </form>
    </div>
@endsection
