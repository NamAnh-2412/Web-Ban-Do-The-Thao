@extends('storefront.layouts.app')

@section('title', 'Lịch thuê của tôi')

@section('content')
    <h1 class="h3 mb-1">Lịch thuê của tôi</h1>
    <p class="text-muted mb-4">Các buổi đang chờ, đã xác nhận, đang thuê hoặc quá hạn. Buổi đã trả không hiện ở đây.</p>

    @forelse ($sessions as $session)
        @php
            $lead = $session['lead'];
            $extendable = $session['bookings']->filter(fn ($row) => in_array($row->status->value, ['confirmed', 'active'], true)
                && ! $row->extensions->contains(fn ($extension) => $extension->status->value === 'pending'));
        @endphp
        <div class="policy-card mb-3">
            <h2 class="h5">Buổi thuê
                @if ($session['item_count'] > 1)
                    ({{ $session['item_count'] }} món)
                @endif
            </h2>
            <p class="small mb-2">
                {{ $lead->start_date->toDateString() }} → {{ $lead->end_date->toDateString() }}
                · <strong>{{ $session['status']->label() }}</strong>
                @if ($lead->order_id)
                    · <a href="{{ route('orders.show', $lead->order_id) }}">Đơn #{{ $lead->order_id }}</a>
                @endif
            </p>
            <ul class="list-unstyled mb-0">
                @foreach ($session['bookings'] as $row)
                    <li class="mb-2">
                        {{ $row->variant?->product?->name ?? $row->variant?->sku ?? 'Món thuê' }}
                        · {{ number_format($row->rental_amount, 0, ',', '.') }}đ
                        @if ((float) $row->deposit_amount > 0)
                            · cọc {{ number_format($row->deposit_amount, 0, ',', '.') }}đ
                        @endif
                        · {{ $row->status->label() }}
                    </li>
                @endforeach
            </ul>
            @if ($extendable->isNotEmpty())
                @include('storefront.partials.rental-extension-form', [
                    'orderId' => $lead->order_id,
                    'lead' => $lead,
                ])
            @endif
        </div>
    @empty
        <div class="alert alert-light border">Chưa có lịch thuê đang mở.</div>
    @endforelse
@endsection
