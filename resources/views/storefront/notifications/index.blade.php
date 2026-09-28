@extends('storefront.layouts.app')

@section('title', 'Thông báo')

@section('content')
    <h1 class="h3 mb-3">Thông báo email</h1>
    <p class="text-secondary">Xác nhận đơn và nhắc hạn trả được xếp hàng gửi email (snapshot địa chỉ lúc gửi, không join User).</p>

    @forelse ($notifications as $row)
        <div class="policy-card mb-3">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <strong>{{ $row->subject }}</strong>
                <span class="small">{{ $row->status->label() }} · {{ $row->type->label() }}</span>
            </div>
            <pre class="small mb-0 mt-2" style="white-space: pre-wrap; font-family: inherit;">{{ $row->body }}</pre>
        </div>
    @empty
        <div class="policy-card">Chưa có thông báo.</div>
    @endforelse
@endsection
