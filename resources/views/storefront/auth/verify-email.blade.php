@extends('storefront.layouts.app')

@section('title', 'Xác thực email')

@section('content')
    <div class="policy-card col-lg-6 mx-auto">
        <h1 class="h4 mb-2">Xác thực email</h1>
        <p class="text-secondary">PDF yêu cầu xác thực người dùng. Bạn cần bấm link trong email trước khi đặt hàng hoặc đánh giá.</p>
        <form method="post" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-success" type="submit">Gửi lại email xác thực</button>
        </form>
    </div>
@endsection
