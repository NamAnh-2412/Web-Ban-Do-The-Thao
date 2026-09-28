<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Support\BankTransferQr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentWriter
{
    /**
     * @return Collection<int, Payment>
     */
    public function createIntents(
        int $orderId,
        int $userId,
        float $goodsAmount,
        float $depositAmount,
        PaymentMethod $method,
    ): Collection {
        return DB::transaction(function () use ($orderId, $userId, $goodsAmount, $depositAmount, $method) {
            $rows = collect();

            if ($goodsAmount > 0) {
                $rows->push($this->firstOrCreateIntent($orderId, $userId, PaymentKind::Merchandise, $goodsAmount, $method));
            }

            if ($depositAmount > 0) {
                $rows->push($this->firstOrCreateIntent($orderId, $userId, PaymentKind::Deposit, $depositAmount, $method));
            }

            return $rows->values();
        });
    }

    public function complete(Payment $payment, ?string $providerTxnId = null, ?string $note = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ hoàn tất khoản pending.'],
            ]);
        }

        if ($payment->kind === PaymentKind::Refund) {
            throw ValidationException::withMessages([
                'kind' => ['Refund không thu thêm.'],
            ]);
        }

        $payment->status = PaymentStatus::Completed;
        $payment->paid_at = now();
        $payment->provider_txn_id = $providerTxnId ?? $payment->provider_txn_id ?? $this->nextTxnId($payment);
        if ($note !== null) {
            $payment->note = trim(($payment->note ? $payment->note."\n" : '').$note);
        }
        $payment->save();

        return $payment->refresh();
    }

    public function fail(Payment $payment, ?string $note = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ fail khoản pending.'],
            ]);
        }

        $payment->status = PaymentStatus::Failed;
        $payment->note = $note ?? $payment->note;
        $payment->save();

        return $payment->refresh();
    }

    public function reopenFailed(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Failed) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ mở lại khoản thất bại.'],
            ]);
        }

        $payment->status = PaymentStatus::Pending;
        $payment->save();

        return $payment->refresh();
    }

    public function cancelPendingByOrder(int $orderId): void
    {
        Payment::query()
            ->where('order_id', $orderId)
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Cancelled->value]);
    }

    public function refundCompletedCollections(int $orderId, int $userId, ?string $note = null): ?Payment
    {
        $paid = (float) Payment::query()
            ->where('order_id', $orderId)
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');

        $already = (float) Payment::query()
            ->where('order_id', $orderId)
            ->where('kind', PaymentKind::Refund)
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');

        $amount = round($paid - $already, 2);
        if ($amount <= 0) {
            return null;
        }

        return $this->refund($orderId, $userId, $amount, $note ?? 'Hoàn tiền khi hủy đơn.');
    }

    public function recordCollected(int $orderId, int $userId, float $amount, string $note): Payment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Số tiền thu phải lớn hơn 0.'],
            ]);
        }

        return Payment::query()->create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'kind' => PaymentKind::Merchandise,
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'provider_txn_id' => 'EXT-'.strtoupper(bin2hex(random_bytes(4))),
            'note' => $note,
        ]);
    }

    public function refundableRemaining(int $orderId): float
    {
        $paid = (float) Payment::query()
            ->where('order_id', $orderId)
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');

        $already = (float) Payment::query()
            ->where('order_id', $orderId)
            ->where('kind', PaymentKind::Refund)
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');

        return round($paid - $already, 2);
    }

    public function refund(int $orderId, int $userId, float $amount, string $note): Payment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Số tiền hoàn phải lớn hơn 0.'],
            ]);
        }

        if ($amount - 0.001 > $this->refundableRemaining($orderId)) {
            throw ValidationException::withMessages([
                'amount' => ['Không hoàn vượt số đã thu.'],
            ]);
        }

        return Payment::query()->create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'kind' => PaymentKind::Refund,
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'provider_txn_id' => 'REF-'.strtoupper(bin2hex(random_bytes(4))),
            'note' => $note,
        ]);
    }

    /** @return Collection<int, Payment> */
    public function listByOrder(int $orderId): Collection
    {
        return Payment::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();
    }

    public function isCollectibleSettled(int $orderId): bool
    {
        $intents = Payment::query()
            ->where('order_id', $orderId)
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->get();

        if ($intents->isEmpty()) {
            return false;
        }

        return $intents->every(fn (Payment $row) => $row->status === PaymentStatus::Completed);
    }

    private function firstOrCreateIntent(
        int $orderId,
        int $userId,
        PaymentKind $kind,
        float $amount,
        PaymentMethod $method,
    ): Payment {
        $existing = Payment::query()
            ->where('order_id', $orderId)
            ->where('kind', $kind)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Completed->value])
            ->first();

        if ($existing) {
            return $existing;
        }

        $qr = $method === PaymentMethod::BankTransfer ? BankTransferQr::display(0, $orderId) : [];
        $note = match ($method) {
            PaymentMethod::BankTransfer => sprintf(
                'Chuyển khoản %s%s — %s. Nội dung: %s',
                $qr['bank_name'] ?? (string) config('payments.bank.name'),
                ($qr['account_number'] ?? '') !== '' ? ' '.$qr['account_number'] : '',
                $qr['account_name'] ?? (string) config('payments.bank.holder'),
                $qr['content'] ?? ((string) config('payments.bank.content_prefix')).$orderId,
            ),
            PaymentMethod::Cash => 'Thanh toán tiền mặt.',
            PaymentMethod::Momo => 'MoMo: thu một lần (hàng/thuê + cọc + ship), rồi phân bổ từng khoản. Không trừ cọc khi áp coupon.',
            PaymentMethod::Cod => 'COD giao nhà: GHN thu hộ cả tổng đơn (cọc gộp). Không COD khi nhận tại quầy.',
            PaymentMethod::Vnpay => 'Cổng VNPay chưa gắn merchant.',
        };

        return Payment::query()->create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'kind' => $kind,
            'method' => $method,
            'amount' => round($amount, 2),
            'status' => PaymentStatus::Pending,
            'note' => $note,
        ]);
    }

    private function nextTxnId(Payment $payment): string
    {
        $prefix = match ($payment->method) {
            PaymentMethod::Cash => 'CASH',
            PaymentMethod::BankTransfer => 'BANK',
            PaymentMethod::Momo => 'MOMO',
            PaymentMethod::Cod => 'COD',
            default => 'TXN',
        };

        return $prefix.'-'.strtoupper(bin2hex(random_bytes(4)));
    }
}
