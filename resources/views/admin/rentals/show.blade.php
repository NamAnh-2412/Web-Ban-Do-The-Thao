@extends('layouts.admin')
@include('partials.js', ['file' => 'js/admin/rental-return.js'])
@section('title', 'Buổi thuê #'.$booking->id)
@php
    $itemCount = $bookings->count();
    $open = $bookings->filter(fn ($row) => in_array($row->status, [
        \App\Domain\Rental\Enums\BookingStatus::Active,
        \App\Domain\Rental\Enums\BookingStatus::Overdue,
    ], true));
    $confirmed = $bookings->where('status', \App\Domain\Rental\Enums\BookingStatus::Confirmed);
    $pending = $bookings->where('status', \App\Domain\Rental\Enums\BookingStatus::Pending);
    $extendable = $bookings->filter(fn ($row) => in_array($row->status, [
        \App\Domain\Rental\Enums\BookingStatus::Confirmed,
        \App\Domain\Rental\Enums\BookingStatus::Active,
    ], true));
@endphp
@section('content')
    <p class="small mb-3">
        <a href="{{ route('admin.rentals.index') }}">← Lịch thuê</a>
        @if ($booking->order_id)
            · <a href="{{ route('admin.orders.show', $booking->order_id) }}">Đơn #{{ $booking->order_id }}</a>
        @endif
    </p>
    <h1 class="page-title">Buổi thuê @if ($itemCount > 1)({{ $itemCount }} món)@else #{{ $booking->id }}@endif</h1>
    <p class="page-subtitle mb-4">
        {{ $booking->user?->name }}
        · {{ $booking->start_date->toDateString() }} → {{ $booking->end_date->toDateString() }}
        · <strong>{{ $sessionStatus->label() }}</strong>
    </p>

    <div class="admin-card mb-3">
        <div class="p-3 fw-semibold">Món trong buổi</div>
        <table class="table admin-table mb-0">
            <thead><tr><th>Lịch</th><th>Sản phẩm</th><th>Mã món</th><th>Thuê</th><th>Cọc</th><th>Trạng thái</th></tr></thead>
            <tbody>
            @foreach ($bookings as $row)
                <tr>
                    <td>#{{ $row->id }}</td>
                    <td>{{ $row->variant?->product?->name ?? $row->variant?->sku }}</td>
                    <td>{{ $row->item?->asset_code ?? '—' }}</td>
                    <td>{{ number_format($row->rental_amount, 0, ',', '.') }}đ</td>
                    <td>{{ number_format($row->deposit_amount, 0, ',', '.') }}đ</td>
                    <td>{{ $row->status->label() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($pending->isNotEmpty())
        <form class="d-inline" method="post" action="{{ route('admin.rentals.confirm', $booking) }}">
            @csrf
            <button class="btn btn-outline-primary">Xác nhận buổi</button>
        </form>
    @endif

    @if ($confirmed->isNotEmpty())
        <div class="alert alert-light border">Online: khách đã thu tiền, bấm <strong>Giao đồ</strong> khi đưa món. Đơn tại quầy đã giao lúc xác nhận thanh toán.</div>
        <form class="d-inline" method="post" action="{{ route('admin.rentals.activate', $booking) }}">
            @csrf
            <button class="btn btn-success">Giao {{ $confirmed->count() > 1 ? 'cả buổi ('.$confirmed->count().' món)' : 'đồ cho khách' }}</button>
        </form>
    @endif

    @if ($extendable->isNotEmpty())
        <div class="admin-card p-4 mt-3" style="max-width:640px">
            <h2 class="h5">Gia hạn buổi</h2>
            <form method="post" action="{{ route('admin.rentals.extensions.store', $booking) }}">
                @csrf
                <label class="form-label" for="new_end_date">Ngày trả mới</label>
                <input class="form-control mb-2" id="new_end_date" name="new_end_date" type="date" required min="{{ $booking->end_date->copy()->addDay()->toDateString() }}">
                <button class="btn btn-outline-secondary">Tạo yêu cầu cho cả buổi</button>
            </form>
            @foreach ($bookings as $row)
                @foreach ($row->extensions as $extension)
                    <div class="border rounded p-2 mt-2 small">
                        #{{ $row->id }} · {{ $extension->old_end_date->toDateString() }} → {{ $extension->new_end_date->toDateString() }}
                        · thêm {{ number_format($extension->extra_amount, 0, ',', '.') }}đ
                        · {{ $extension->status->value }}
                        @if ($extension->status->value === 'pending')
                            <form class="d-inline" method="post" action="{{ route('admin.rentals.extensions.collect', [$row, $extension]) }}">
                                @csrf
                                <button class="btn btn-sm btn-success">Thu thêm và duyệt</button>
                            </form>
                            <form class="d-inline" method="post" action="{{ route('admin.rentals.extensions.reject', [$row, $extension]) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif

    @if ($open->isNotEmpty())
        <div class="admin-card p-4 mt-3" style="max-width:720px">
            <h2 class="h5">Khách mang đồ trả — xác nhận cả buổi</h2>
            <p class="small text-muted">Nguyên vẹn thì hoàn đủ cọc (trừ trả muộn). Hư / mất: nhập tiền đền bù, hệ thống trừ vào cọc. Mất mà để trống phí thì trừ hết cọc. Phí lớn hơn cọc thì khách còn đền thêm.</p>
            <form method="post" action="{{ route('admin.rentals.return', $booking) }}">
                @csrf
                @foreach ($open as $row)
                    <div class="border rounded p-3 mb-3" data-return-item data-deposit="{{ (int) $row->deposit_amount }}">
                        <div class="fw-semibold mb-2">#{{ $row->id }} · {{ $row->variant?->product?->name ?? $row->variant?->sku }} · {{ $row->item?->asset_code }}</div>
                        <label class="form-label" for="condition-{{ $row->id }}">Tình trạng</label>
                        <select name="items[{{ $row->id }}][condition]" id="condition-{{ $row->id }}" class="form-select mb-2" data-return-condition>
                            <option value="good">Nguyên vẹn</option>
                            <option value="damaged">Hư hỏng</option>
                            <option value="lost">Mất</option>
                        </select>
                        <input class="form-control mb-2" name="items[{{ $row->id }}][notes]" placeholder="Ghi chú lúc trả (không bắt buộc)">
                        <div class="return-compensation" hidden>
                            <label class="form-label" for="incident-desc-{{ $row->id }}">Mô tả hỏng / mất</label>
                            <input class="form-control mb-2" id="incident-desc-{{ $row->id }}" name="items[{{ $row->id }}][incident_description]" placeholder="Ví dụ: rách lưới, mất vợt">
                            <label class="form-label" for="incident-fee-{{ $row->id }}">Tiền đền bù (trừ vào cọc {{ number_format($row->deposit_amount, 0, ',', '.') }}đ)</label>
                            <input class="form-control" id="incident-fee-{{ $row->id }}" name="items[{{ $row->id }}][incident_fee]" type="number" min="0" step="1000" data-return-fee placeholder="Số tiền đền">
                            <div class="form-text">Mất: để trống = giữ hết cọc. Hư: nhập số cần trừ.</div>
                        </div>
                    </div>
                @endforeach
                <button class="btn btn-admin-primary" type="submit">Xác nhận trả {{ $open->count() > 1 ? 'cả buổi' : 'đồ' }}</button>
            </form>
        </div>
    @endif

    @if ($sessionStatus === \App\Domain\Rental\Enums\BookingStatus::Returned)
        <div class="alert alert-success">Đã xác nhận trả buổi thuê.</div>
        <div class="admin-card p-4 mb-3" style="max-width:720px">
            <h2 class="h5">Cọc và đền bù</h2>
            @foreach ($bookings as $row)
                @include('partials.rental_compensation', [
                    'incidents' => $row->incidents,
                    'outcome' => $outcomes[$row->id] ?? null,
                ])
                @if ($row->incidents->isEmpty() && (float) ($outcomes[$row->id]['fees_total'] ?? 0) <= 0)
                    <p class="small text-muted mb-2">#{{ $row->id }} · không sự cố · hoàn đủ cọc {{ number_format($row->deposit_amount, 0, ',', '.') }}đ</p>
                @endif
            @endforeach
        </div>
        @if ($depositSuggested > 0)
            <form method="post" action="{{ route('admin.rentals.deposit-refund', $booking) }}" onsubmit="return confirm('Hoàn cọc {{ number_format($depositSuggested, 0, ',', '.') }}đ cho buổi này?')">
                @csrf
                <button class="btn btn-outline-danger">Hoàn cọc {{ number_format($depositSuggested, 0, ',', '.') }}đ</button>
            </form>
        @else
            <p class="small text-muted">Đã hoàn cọc buổi này, đã trừ hết cọc do sự cố, hoặc không còn cọc để hoàn.</p>
        @endif
    @endif
@endsection
