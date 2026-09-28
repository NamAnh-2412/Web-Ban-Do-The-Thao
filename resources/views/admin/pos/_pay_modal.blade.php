@php
    $payTotal = (int) round($grand_total);
    $payQty = collect($lines)->sum(fn ($line) => max(1, (int) ($line['quantity'] ?? 1)));
@endphp
<div class="pos-pay-overlay" id="pos-pay-overlay" hidden>
    <div class="pos-pay-dialog" role="dialog" aria-modal="true" aria-labelledby="pos-pay-title" data-pay-total="{{ $payTotal }}">
        <header class="pos-pay-head">
            <h2 id="pos-pay-title">Thanh toán</h2>
            <button type="button" class="pos-pay-x" data-pos-pay-close aria-label="Đóng">&times;</button>
        </header>
        <div class="pos-pay-body">
            <nav class="pos-pay-methods-col" aria-label="Hình thức thanh toán">
                <button type="button" class="pos-pay-method is-active" data-pay-method="cash">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Tiền mặt</span>
                </button>
                <button type="button" class="pos-pay-method" data-pay-method="bank_transfer">
                    <i class="fas fa-university"></i>
                    <span>Chuyển khoản</span>
                </button>
                <button type="button" class="pos-pay-method is-soon" disabled title="Sẽ làm ở bước sau">
                    <i class="fas fa-credit-card"></i>
                    <span>Thẻ</span>
                </button>
                <button type="button" class="pos-pay-method is-soon" disabled title="Sẽ làm ở bước sau">
                    <i class="fas fa-mobile-alt"></i>
                    <span>App online</span>
                </button>
            </nav>
            <div class="pos-pay-main">
                <div class="pos-pay-row">
                    <span>Tổng tiền ({{ $payQty }})</span>
                    <strong class="pos-pay-due" id="pos-pay-due">{{ number_format($payTotal, 0, ',', '.') }} đ</strong>
                </div>
                <div class="pos-pay-cash">
                    <div class="pos-pay-row">
                        <label for="pos-pay-tendered">Khách trả <span class="pos-pay-req">*</span></label>
                        <input id="pos-pay-tendered" type="text" inputmode="numeric" value="{{ number_format($payTotal, 0, ',', '.') }}" autocomplete="off">
                    </div>
                    <div class="pos-pay-row">
                        <span>Tiền thừa</span>
                        <strong class="pos-pay-change" id="pos-pay-change">0 đ</strong>
                    </div>
                    <div class="pos-pay-keys" role="group" aria-label="Bàn phím số">
                        @foreach ([7, 8, 9, 4, 5, 6, 1, 2, 3] as $digit)
                            <button type="button" data-pay-key="{{ $digit }}">{{ $digit }}</button>
                        @endforeach
                        <button type="button" data-pay-key="000">000</button>
                        <button type="button" data-pay-key="0">0</button>
                        <button type="button" data-pay-key="back" aria-label="Xóa số">&larr;</button>
                    </div>
                </div>
                <div class="pos-pay-qr" hidden>
                    <p class="small text-secondary mb-2">Khách quét QR MoMo hoặc app ngân hàng. Khi đã thấy tiền, bấm XÁC NHẬN để chốt như tiền mặt.</p>
                    @include('partials.bank_transfer_qr', ['qr' => $bankQr ?? [], 'compact' => true])
                </div>
            </div>
        </div>
        <footer class="pos-pay-foot">
            <button type="button" class="pos-pay-close" data-pos-pay-close>ĐÓNG</button>
            <button type="button" class="pos-pay-confirm" id="pos-pay-confirm">XÁC NHẬN</button>
        </footer>
    </div>
</div>
