<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryStockReservation;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Gateway\Services\CheckoutOrchestrator;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'webthethao:release-expired';

    protected $description = 'Hủy đơn online pending khi reservation bán đã hết hạn, nhả tồn kho.';

    public function handle(CheckoutOrchestrator $checkout): int
    {
        $orderIds = InventoryStockReservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereHas('order', function ($query) {
                $query->where('channel', OrderChannel::Online)
                    ->where('status', OrderStatus::Pending);
            })
            ->pluck('order_id')
            ->unique()
            ->filter();

        $cancelled = 0;
        foreach ($orderIds as $orderId) {
            $order = Order::query()->with(['items', 'bookings'])->find($orderId);
            if ($order === null || ! $order->canBeCancelled()) {
                continue;
            }
            $checkout->cancel($order);
            $cancelled++;
        }

        $this->info("Đã hủy {$cancelled} đơn online hết hạn giữ kho.");

        return self::SUCCESS;
    }
}
