<?php

namespace App\Domain\Report\Services;

use App\Domain\Coupon\Models\CouponRedemption;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Review\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /** @return list<OrderStatus> */
    public function settledStatuses(): array
    {
        return [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Completed];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(?string $from, ?string $to): array
    {
        $start = Carbon::parse($from ?: now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($to ?: now()->toDateString())->endOfDay();

        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $paid = $orders->filter(fn (Order $order) => in_array($order->status, $this->settledStatuses(), true));
        $paidIds = $paid->pluck('id');

        $merchandise = (float) $paid->sum('merchandise_total');
        $rental = (float) $paid->sum('rental_total');
        $discount = (float) $paid->sum('discount_total');
        $deposit = (float) $paid->sum('deposit_total');
        $goods = round($merchandise + $rental - $discount, 2);

        $refunds = $this->refundsForOrders($paidIds);
        $depositRefunded = (float) $refunds
            ->filter(fn (Payment $payment) => $this->isDepositRefund($payment))
            ->sum('amount');
        $otherRefunds = (float) $refunds
            ->reject(fn (Payment $payment) => $this->isDepositRefund($payment))
            ->sum('amount');

        $topProducts = OrderItem::query()
            ->selectRaw('product_id, product_name, line_type, SUM(quantity) as qty, SUM(line_total) as revenue')
            ->whereIn('order_id', $paidIds)
            ->groupBy('product_id', 'product_name', 'line_type')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'order_total' => $orders->count(),
            'order_paid' => $paid->count(),
            'order_cancelled' => $orders->where('status', OrderStatus::Cancelled)->count(),
            'merchandise' => $merchandise,
            'rental' => $rental,
            'deposit' => $deposit,
            'discount' => $discount,
            'goods' => $goods,
            'deposit_refunded' => $depositRefunded,
            'deposit_outstanding' => round(max(0, $deposit - $depositRefunded), 2),
            'other_refunds' => $otherRefunds,
            'revenue' => round(max(0, $goods - $otherRefunds), 2),
            'collected' => (float) $paid->sum('grand_total'),
            'online_revenue' => $this->channelRevenue($paid, OrderChannel::Online),
            'pos_revenue' => $this->channelRevenue($paid, OrderChannel::Pos),
            'top_products' => $topProducts,
            'coupons' => CouponRedemption::query()
                ->with('coupon')
                ->whereBetween('created_at', [$start, $end])
                ->get(),
            'reviews_count' => Review::query()->whereBetween('created_at', [$start, $end])->count(),
            'reviews_avg' => round((float) Review::query()->whereBetween('created_at', [$start, $end])->avg('rating'), 2),
            'low_stocks' => InventoryStock::query()
                ->with('variant.product')
                ->get()
                ->filter(fn (InventoryStock $stock) => $stock->isLow())
                ->values(),
        ];
    }

    /** @return array{labels: list<string>, values: list<float>} */
    public function weekRevenue(): array
    {
        $from = now()->subDays(6)->startOfDay();
        $to = now()->endOfDay();

        $byDay = Order::query()
            ->whereIn('status', $this->settledStatuses())
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, SUM(merchandise_total + rental_total - discount_total) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->pluck('revenue', 'day');

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d/m');
            $values[] = (float) ($byDay[$day->toDateString()] ?? 0);
        }

        return compact('labels', 'values');
    }

    /** @param  Collection<int, Order>  $orders */
    private function channelRevenue(Collection $orders, OrderChannel $channel): float
    {
        return round((float) $orders
            ->where('channel', $channel)
            ->sum(fn (Order $order) => $order->revenueAmount()), 2);
    }

    /** @param  Collection<int, int|string>  $orderIds */
    private function refundsForOrders(Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->whereIn('order_id', $orderIds)
            ->where('kind', PaymentKind::Refund)
            ->where('status', PaymentStatus::Completed)
            ->get();
    }

    private function isDepositRefund(Payment $payment): bool
    {
        return is_string($payment->note) && str_starts_with($payment->note, 'Hoàn cọc');
    }
}
