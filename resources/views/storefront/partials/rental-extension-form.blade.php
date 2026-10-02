@if ($orderId)
    <form class="border rounded p-3 mt-3" method="post" action="{{ route('orders.extensions.store', $orderId) }}">
        @csrf
        <input type="hidden" name="booking_id" value="{{ $lead->id }}">
        <p class="small mb-2">Gia hạn cả buổi — cửa hàng sẽ thu thêm rồi duyệt.</p>
        <label class="form-label" for="new_end_date_{{ $lead->id }}">Ngày trả mới</label>
        <input class="form-control mb-2" style="max-width:12rem" id="new_end_date_{{ $lead->id }}" name="new_end_date" type="date" required min="{{ $lead->end_date->copy()->addDay()->toDateString() }}">
        <button class="btn btn-sm btn-outline-primary" type="submit">Gửi yêu cầu gia hạn</button>
    </form>
@endif
