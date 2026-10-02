@extends('storefront.layouts.app')

@section('title', 'Đơn #'.$order->id)

@php
    $saleItems = $order->items->where('line_type', \App\Domain\Order\Enums\LineType::Sale);
    $sessions = $rentalSessions ?? collect();
@endphp

@section('content')
    <nav class="small mb-3"><a href="{{ route('orders.index') }}">Đơn hàng</a> / #{{ $order->id }}</nav>
    <div class="policy-card mb-3">
        <h1 class="h4">Đơn #{{ $order->id }}
            <span class="badge text-bg-light">{{ $order->customerKindLabel() }}</span>
        </h1>
        <p>Trạng thái: <strong>{{ $order->status->label() }}</strong> · Kênh: {{ $order->channel->label() }}
            @if ($order->shipping_method)
                · {{ $order->shipping_method->label() }}
            @endif
        </p>
        @if ($order->fulfillment_status)
            <p class="small mb-2">Giao hàng: <strong>{{ $order->fulfillment_status->label() }}</strong>
                @if ($order->ghn_order_code)
                    · mã GHN <code>{{ $order->ghn_order_code }}</code>
                @endif
            </p>
        @endif
        @if ($order->status === \App\Domain\Order\Enums\OrderStatus::Pending)
            <p class="small text-warning mb-2">Cửa hàng chưa chốt đơn. Bạn có thể thanh toán trước; kho đã khóa.</p>
        @endif
        <p class="small">{{ $order->shipping_name }} · {{ $order->shipping_phone }}<br>{{ $order->shipping_address }}</p>

        @if ($saleItems->isNotEmpty())
            <h2 class="h6 mt-3">Mua</h2>
            <ul class="list-unstyled mb-0">
                @foreach ($saleItems as $item)
                    <li class="mb-2">
                        {{ $item->product_name }} ({{ $item->sku }}) × {{ $item->quantity }}
                        — {{ number_format($item->line_total, 0, ',', '.') }}đ
                    </li>
                @endforeach
            </ul>
        @endif

        @if ((float) $order->merchandise_total > 0)
            <div class="mt-2">Hàng: {{ number_format($order->merchandise_total, 0, ',', '.') }}đ</div>
        @endif
        @if ((float) $order->rental_total > 0)
            <div>Thuê: {{ number_format($order->rental_total, 0, ',', '.') }}đ</div>
        @endif
        @if ((float) $order->deposit_total > 0)
            <div>Cọc: {{ number_format($order->deposit_total, 0, ',', '.') }}đ</div>
        @endif
        @if ((float) $order->discount_total > 0)
            <div class="text-success">Giảm ({{ $order->coupon_code }}): −{{ number_format($order->discount_total, 0, ',', '.') }}đ</div>
        @endif
        @if ((float) $order->shipping_fee > 0)
            <div>Ship: {{ number_format($order->shipping_fee, 0, ',', '.') }}đ</div>
        @endif
        <div class="fw-bold">Tổng {{ number_format($order->grand_total, 0, ',', '.') }}đ</div>
    </div>

    @foreach ($sessions as $session)
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
            <p class="small mb-2">{{ $lead->start_date->toDateString() }} → {{ $lead->end_date->toDateString() }}
                · <strong>{{ $session['status']->label() }}</strong>
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
                        @if ($row->incidents->isNotEmpty())
                            <div class="mt-2">
                                @include('partials.rental_compensation', [
                                    'incidents' => $row->incidents,
                                    'outcome' => $depositOutcomes[$row->id] ?? null,
                                    'compact' => true,
                                ])
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($extendable->isNotEmpty())
                @include('storefront.partials.rental-extension-form', [
                    'orderId' => $order->id,
                    'lead' => $lead,
                ])
            @endif
        </div>
    @endforeach

    @if (in_array($order->status->value, ['paid', 'processing', 'completed'], true))
        <div class="policy-card mb-3">
            <h2 class="h5">Đánh giá sản phẩm</h2>
            @foreach ($order->items as $item)
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2">
                        <span class="badge text-bg-light">{{ $item->line_type->value === 'sale' ? 'Mua' : 'Thuê' }}</span>
                        {{ $item->product_name }}
                    </div>
                    @if ($item->review)
                        <p class="mb-0">Bạn đã đánh giá {{ $item->review->rating }}/5
                            @if ($item->review->comment) — {{ $item->review->comment }} @endif
                        </p>
                    @else
                        <form method="post" action="{{ route('orders.review', $order->id) }}">
                            @csrf
                            <input type="hidden" name="order_item_id" value="{{ $item->id }}">
                            <div class="mb-2">
                                <label class="form-label" for="rating-{{ $item->id }}">Số sao</label>
                                <select class="form-select" style="max-width: 8rem" id="rating-{{ $item->id }}" name="rating" required>
                                    @for ($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}">{{ $i }} sao</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="comment-{{ $item->id }}">Nhận xét</label>
                                <textarea class="form-control" id="comment-{{ $item->id }}" name="comment" rows="2"></textarea>
                            </div>
                            <button class="btn btn-sm btn-success" type="submit">Gửi đánh giá</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="policy-card">
        <h2 class="h5">Thanh toán (tiền mua/thuê và cọc tách khoản)</h2>
        @if (! empty($bankQr) && (float) $bankQr['amount'] > 0 && $payments->contains(fn ($p) => $p->method->value === 'bank_transfer' && $p->status->value === 'pending'))
            <div class="mb-3">
                <p class="fw-semibold mb-2">Quét QR MoMo / ngân hàng</p>
                @include('partials.bank_transfer_qr', ['qr' => $bankQr])
                <p class="small text-secondary mb-0 mt-2">Chờ nhân viên xác nhận đã nhận tiền.</p>
            </div>
        @endif
        @if ($order->canRetryGateway() && $payments->contains(fn ($p) => $p->method->value === 'momo' && $p->status->value === 'pending'))
            <form class="mb-3" method="post" action="{{ route('orders.momo', $order->id) }}">
                @csrf
                <button class="btn btn-success" type="submit">Thanh toán lại MoMo</button>
            </form>
        @endif
        @forelse ($payments as $payment)
            @php
                $kindLabel = match ($payment->kind->value) {
                    'merchandise' => 'Tiền mua + thuê',
                    'deposit' => 'Cọc',
                    'refund' => 'Hoàn tiền',
                    default => $payment->kind->value,
                };
            @endphp
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <strong>{{ $kindLabel }}</strong>
                    <span>{{ number_format($payment->amount, 0, ',', '.') }}đ · {{ $payment->status->label() }} · {{ $payment->method->label() }}</span>
                </div>
                @if ($payment->note)
                    <p class="small mb-2 mt-2">{{ $payment->note }}</p>
                @endif
                @if ($payment->status->value === 'pending' && $payment->method->value === 'bank_transfer')
                    <p class="small text-secondary mb-0">Chờ nhân viên xác nhận đã nhận tiền.</p>
                @endif
            </div>
        @empty
            <p class="small text-secondary mb-0">Chưa có khoản thanh toán.</p>
        @endforelse
    </div>
@endsection
