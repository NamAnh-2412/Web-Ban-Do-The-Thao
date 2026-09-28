<?php

namespace App\Domain\Payment\Support;

use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentQrSetting;

class BankTransferQr
{
    /**
     * @return array{
     *     bank_name: string,
     *     account_name: string,
     *     account_number: string,
     *     image_url: ?string,
     *     instructions: ?string,
     *     content: string,
     *     amount: float
     * }
     */
    public static function display(float $amount = 0, ?int $orderId = null): array
    {
        $row = PaymentQrSetting::current();

        return [
            'bank_name' => $row?->bank_name ?: (string) config('payments.bank.name'),
            'account_name' => $row?->account_name ?: (string) config('payments.bank.holder'),
            'account_number' => $row !== null ? (string) $row->account_number : (string) config('payments.bank.account'),
            'image_url' => $row?->imageUrl(),
            'instructions' => $row?->instructions,
            'content' => self::transferContent($orderId),
            'amount' => round($amount, 2),
        ];
    }

    public static function forOrder(int $orderId): array
    {
        return self::display(self::pendingAmount($orderId), $orderId);
    }

    public static function transferContent(?int $orderId): string
    {
        $prefix = (string) config('payments.bank.content_prefix', 'WTT');

        return $orderId ? $prefix.$orderId : $prefix;
    }

    public static function pendingAmount(int $orderId): float
    {
        return (float) Payment::query()
            ->where('order_id', $orderId)
            ->where('method', PaymentMethod::BankTransfer)
            ->where('status', PaymentStatus::Pending)
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->sum('amount');
    }
}
