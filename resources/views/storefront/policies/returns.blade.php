@extends('storefront.layouts.app')

@section('title', 'Chính sách đổi trả')

@section('content')
    <div class="policy-card col-lg-8">
        <h1 class="h3 mb-3">Đổi trả hàng mua</h1>
        <p>Áp dụng cho dòng <strong>bán</strong>. Hàng thuê không đổi trả theo chính sách này — xem mục thuê và cọc.</p>
        <ul>
            <li>Đổi size / màu trong 7 ngày nếu còn tem, chưa dùng sân, hộp đầy đủ.</li>
            <li>Lỗi nhà sản xuất: đổi mới hoặc hoàn tiền phần hàng (không gồm phí vận chuyển nếu khách đổi ý).</li>
            <li>Không nhận lại đồ đã cắt tem, ngấm nước, mùi lạ hoặc trầy đế rõ.</li>
        </ul>
        <p class="mb-0">Đơn hàng lưu snapshot tên/giá/ảnh lúc mua nên lịch sử đơn không phụ thuộc catalog sau này.</p>
    </div>
@endsection
