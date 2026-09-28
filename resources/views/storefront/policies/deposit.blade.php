@extends('storefront.layouts.app')

@section('title', 'Chính sách cọc')

@section('content')
    <div class="policy-card col-lg-8">
        <h1 class="h3 mb-3">Tiền cọc khi thuê</h1>
        <p>Cọc là khoản riêng, không trộn với tiền thuê. Cửa hàng hoàn sau khi nhận đồ và kiểm tra.</p>
        <ul>
            <li>Mức cọc ghi trên từng món ở chế độ Thuê.</li>
            <li>Trả nguyên vẹn, đúng hạn: hoàn đủ cọc.</li>
            <li>Hư hỏng / mất / trả muộn: trừ tiền đền bù vào cọc. Phí lớn hơn cọc thì khách đền thêm phần còn thiếu.</li>
        </ul>
        <a href="{{ route('policies.rental') }}">Quay lại chính sách thuê</a>
    </div>
@endsection
