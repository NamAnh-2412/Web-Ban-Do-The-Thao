@extends('layouts.admin')
@section('title', 'Thanh toán')
@section('content')
    <h1 class="page-title">Thanh toán</h1>
    <p class="page-subtitle mb-4">Sổ từng khoản thu / hoàn. Mã đơn trùng cột Mã đơn bên Đơn hàng.</p>

    <div class="admin-card p-4 mb-4" style="max-width:640px">
        <h2 class="h5">QR chuyển khoản</h2>
        <p class="small text-muted">Tải ảnh QR MoMo / VietQR của cửa hàng. Khách quét trên đơn hoặc tại quầy; nhân viên vẫn bấm xác nhận khi đã nhận tiền.</p>
        @if (!empty($qr['image_url']))
            <img src="{{ $qr['image_url'] }}" alt="QR chuyển khoản" class="mb-3 border rounded" style="max-width:180px">
        @endif
        <form method="post" action="{{ route('admin.payments.qr') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-2">
                <label class="form-label" for="bank_name">Ngân hàng / ví</label>
                <input class="form-control @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" value="{{ old('bank_name', $qr['bank_name']) }}" required>
                @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-2">
                <label class="form-label" for="account_name">Chủ tài khoản</label>
                <input class="form-control @error('account_name') is-invalid @enderror" id="account_name" name="account_name" value="{{ old('account_name', $qr['account_name']) }}" required>
                @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-2">
                <label class="form-label" for="account_number">Số tài khoản (tuỳ chọn nếu khách chỉ quét QR)</label>
                <input class="form-control @error('account_number') is-invalid @enderror" id="account_number" name="account_number" value="{{ old('account_number', $qr['account_number']) }}">
                @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-2">
                <label class="form-label" for="qr_image">Ảnh QR {{ empty($qr['image_url']) ? '' : '(để trống nếu giữ ảnh cũ)' }}</label>
                <input class="form-control @error('qr_image') is-invalid @enderror" id="qr_image" name="qr_image" type="file" accept="image/*" @required(empty($qr['image_url']))>
                @error('qr_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="instructions">Hướng dẫn (tuỳ chọn)</label>
                <textarea class="form-control" id="instructions" name="instructions" rows="2">{{ old('instructions', $qr['instructions'] ?? '') }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">Lưu QR</button>
        </form>
    </div>

    <div class="admin-card">
        <table class="table admin-table">
            <thead><tr><th>Mã khoản</th><th>Đơn</th><th>Khách</th><th>Loại</th><th>Số tiền</th><th>TT</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>#{{ $payment->id }}</td>
                    <td><a href="{{ route('admin.orders.show', $payment->order_id) }}">#{{ $payment->order_id }}</a></td>
                    <td>{{ $payment->user?->name }}</td>
                    <td>{{ $payment->kind->label() }}</td>
                    <td>{{ number_format($payment->amount, 0, ',', '.') }}đ</td>
                    <td>{{ $payment->status->label() }}</td>
                    <td>
                        @if ($payment->status->value === 'pending')
                            <form method="post" action="{{ route('admin.payments.confirm', $payment) }}">@csrf<button class="btn btn-sm btn-success">Xác nhận</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có khoản thanh toán.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $payments->links() }}
    <div class="admin-card p-4 mt-4" style="max-width:520px">
        <h5>Hoàn tiền</h5>
        <p class="small text-muted">Nhập số mã đơn (ví dụ 12). Hệ thống lấy khách từ đơn đó.</p>
        <form method="post" action="{{ route('admin.payments.refund') }}">
            @csrf
            <div class="mb-2"><label class="form-label" for="order_id">Mã đơn</label><input class="form-control" id="order_id" name="order_id" required placeholder="Ví dụ: 12"></div>
            <div class="mb-2"><label class="form-label" for="refund_amount">Số tiền</label><input class="form-control" id="refund_amount" name="amount" type="number" min="1" step="1000" required></div>
            <div class="mb-2"><label class="form-label" for="refund_note">Ghi chú</label><input class="form-control" id="refund_note" name="note" required></div>
            <button class="btn btn-outline-danger">Hoàn</button>
        </form>
    </div>
@endsection
