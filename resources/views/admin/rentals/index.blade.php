@extends('layouts.admin')
@section('title', 'Lịch thuê')
@section('content')
    <h1 class="page-title">Lịch thuê</h1>
    <p class="page-subtitle mb-3">Mỗi dòng là một buổi (cùng khách, cùng khoảng ngày). Giao / trả cả buổi trên một màn.</p>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <select name="status" class="form-select">
                <option value="">Mọi trạng thái</option>
                @foreach (\App\Domain\Rental\Enums\BookingStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Lọc</button></div>
    </form>
    <div class="admin-card">
        <table class="table admin-table">
            <thead><tr><th>Buổi</th><th>Khách</th><th>Món</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            @forelse ($sessions as $session)
                @php
                    $lead = $session['lead'];
                    $names = $session['bookings']->map(fn ($row) => $row->variant?->sku)->filter()->unique()->values();
                @endphp
                <tr>
                    <td>#{{ $lead->id }}@if ($session['item_count'] > 1) <span class="text-muted">· {{ $session['item_count'] }} món</span>@endif</td>
                    <td>{{ $lead->user?->name }}</td>
                    <td>{{ $names->take(3)->implode(', ') }}@if ($names->count() > 3) …@endif</td>
                    <td>{{ $lead->start_date->toDateString() }} → {{ $lead->end_date->toDateString() }}</td>
                    <td>{{ $session['status']->label() }}</td>
                    <td>
                        <a href="{{ route('admin.rentals.show', $lead) }}">
                            @if ($session['status'] === \App\Domain\Rental\Enums\BookingStatus::Confirmed)
                                Giao đồ
                            @elseif (in_array($session['status'], [\App\Domain\Rental\Enums\BookingStatus::Active, \App\Domain\Rental\Enums\BookingStatus::Overdue], true))
                                Xác nhận trả
                            @else
                                Xem
                            @endif
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Không có lịch thuê.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $sessions->links() }}
@endsection
