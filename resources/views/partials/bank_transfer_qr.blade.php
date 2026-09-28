@php
    $qr = $qr ?? [];
    $compact = ! empty($compact);
    $amount = (float) ($qr['amount'] ?? 0);
    $amountDigits = (string) (int) round($amount);
    $accountNumber = trim((string) ($qr['account_number'] ?? ''));
    $showAccount = $accountNumber !== '' && preg_match('/^\d{6,}$/', $accountNumber);
@endphp
<div class="bank-qr {{ $compact ? 'bank-qr--compact' : '' }}">
    @if (! empty($qr['image_url']))
        <img src="{{ $qr['image_url'] }}" alt="QR chuyển khoản MoMo VietQR" class="bank-qr-img">
    @endif
    <dl class="bank-qr-meta mb-0">
        <div>
            <dt>Ngân hàng / ví</dt>
            <dd>{{ $qr['bank_name'] ?? '' }}</dd>
        </div>
        <div>
            <dt>Chủ tài khoản</dt>
            <dd>{{ $qr['account_name'] ?? '' }}</dd>
        </div>
        @if ($showAccount)
            <div>
                <dt>Số tài khoản</dt>
                <dd>
                    <span>{{ $accountNumber }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $accountNumber }}">Sao chép</button>
                </dd>
            </div>
        @endif
        <div>
            <dt>Số tiền</dt>
            <dd>
                <span>{{ number_format($amount, 0, ',', '.') }}đ</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $amountDigits }}">Sao chép</button>
            </dd>
        </div>
        <div>
            <dt>Nội dung</dt>
            <dd>
                <span>{{ $qr['content'] ?? '' }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $qr['content'] ?? '' }}">Sao chép</button>
            </dd>
        </div>
    </dl>
    @if (! empty($qr['instructions']))
        <p class="small text-secondary mb-0 mt-2">{{ $qr['instructions'] }}</p>
    @endif
</div>
@include('partials.css', ['file' => 'css/shared/bank-qr.css'])
@include('partials.js', ['file' => 'js/shared/copy.js'])
