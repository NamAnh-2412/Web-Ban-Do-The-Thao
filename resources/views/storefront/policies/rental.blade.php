@extends('storefront.layouts.app')

@section('title', 'Chính sách thuê')

@section('content')
    <div class="policy-card col-lg-8">
        <h1 class="h3 mb-3">Chính sách thuê đồ</h1>
        <p>WebTheThao cho thuê dụng cụ, giày và thiết bị theo từng món.</p>
        <ul>
            <li>Khách chọn ngày nhận và ngày trả trên trang sản phẩm. Chỉ đặt được khi món còn trống trong những ngày đó.</li>
            <li>Cần giấy tờ khi nhận đồ (CCCD). Nhân viên ghi tình trạng lúc giao và lúc trả.</li>
            <li>Trả muộn tính thêm theo ngày. Hư hoặc mất: nhân viên ghi sự cố và trừ tiền đền bù vào cọc.</li>
            <li>Không tự ý cho người khác mượn lại món đang thuê.</li>
        </ul>
        <a href="{{ route('policies.deposit') }}">Xem tiền cọc</a>
    </div>
@endsection
