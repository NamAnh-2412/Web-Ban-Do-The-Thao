<?php

namespace App\Gateway\Services;

use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentOrchestrator
{
    public function __construct(
        private PaymentWriter $payments,
        private CheckoutOrchestrator $checkout,
    ) {}

    public function complete(Payment $payment, ?string $providerTxnId = null, ?string $note = null): Payment
    {
        $payment = $this->payments->complete($payment, $providerTxnId, $note);
        $this->settleOrderIfReady($payment->order_id);

        return $payment->refresh();
    }

    public function collectCash(Payment $payment): Payment
    {
        if ($payment->method !== PaymentMethod::Cash) {
            throw ValidationException::withMessages([
                'method' => ['Chỉ thu tiền mặt trên khoản method=cash. Chuyển khoản do nhân viên xác nhận.'],
            ]);
        }

        return $this->complete($payment, null, 'Đã thu tiền mặt.');
    }

    public function confirmReceived(Payment $payment, ?string $providerTxnId = null): Payment
    {
        return $this->complete($payment, $providerTxnId, 'Nhân viên xác nhận đã nhận tiền.');
    }

    /**
     * MoMo (và cổng khác): chốt đơn nếu đang pending, thu hết khoản hàng/cọc, trừ kho.
     * Một lần thu được phân bổ từng Payment.
     */
    public function captureGateway(Order $order, ?string $providerTxnId, string $note): Order
    {
        return DB::transaction(function () use ($order, $providerTxnId, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Completed], true)) {
                return $order;
            }

            if ($order->status === OrderStatus::Pending) {
                $order = $this->checkout->confirmByShop($order);
            }

            $pending = Payment::query()
                ->where('order_id', $order->id)
                ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
                ->where('status', PaymentStatus::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($pending as $payment) {
                $this->payments->complete($payment, $providerTxnId, $note);
            }

            $this->settleOrderIfReady($order->id);

            return $order->fresh() ?? $order;
        });
    }

    public function refund(int $orderId, int $userId, float $amount, string $note): Payment
    {
        return $this->payments->refund($orderId, $userId, $amount, $note);
    }

    /**
     * POS: thu mọi khoản pending rồi trừ kho. Online: chỉ chốt khi đã thu đủ từng khoản.
     *
     * @return Collection<int, Payment>
     */
    public function settleOrder(Order $order): Collection
    {
        if ($order->channel === OrderChannel::Pos) {
            if ($order->status === OrderStatus::Pending) {
                $order = $this->checkout->confirmByShop($order);
            }

            $rows = $this->payments->listByOrder($order->id);
            foreach ($rows as $row) {
                if ($row->status === PaymentStatus::Pending && $row->kind !== PaymentKind::Refund) {
                    $note = $row->method === PaymentMethod::Cash
                        ? 'Đã thu tiền mặt tại quầy.'
                        : ($row->method === PaymentMethod::BankTransfer
                            ? 'Đã nhận chuyển khoản tại quầy.'
                            : 'Admin ghi nhận đã thu đủ.');
                    $this->payments->complete($row, null, $note);
                }
            }
        } elseif (! $this->payments->isCollectibleSettled($order->id)) {
            throw ValidationException::withMessages([
                'status' => ['Chưa thu đủ. Xác nhận từng khoản (tiền hàng / cọc) trước khi chốt đơn online.'],
            ]);
        } elseif ($order->status === OrderStatus::Pending) {
            $order = $this->checkout->confirmByShop($order);
        }

        $this->settleOrderIfReady($order->id);

        return $this->payments->listByOrder($order->id);
    }

    public function confirmByShop(Order $order): Order
    {
        $order = $this->checkout->confirmByShop($order);
        $this->settleOrderIfReady($order->id);

        return $order->fresh(['items', 'user']);
    }

    public function settleOrderIfReady(int $orderId): void
    {
        if (! $this->payments->isCollectibleSettled($orderId)) {
            return;
        }

        $order = Order::query()->find($orderId);
        if ($order === null || $order->status !== OrderStatus::Confirmed) {
            return;
        }

        $this->checkout->markPaid($order);
    }
}
