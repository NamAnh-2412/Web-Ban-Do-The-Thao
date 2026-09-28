<?php

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\Shipping\Enums\ShippingMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWriter
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function create(int $userId, array $meta, array $items): Order
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'lines' => ['Giỏ hàng trống.'],
            ]);
        }

        $merchandise = 0.0;
        $rental = 0.0;
        $deposit = 0.0;

        foreach ($items as $item) {
            $type = $item['line_type'] instanceof LineType ? $item['line_type'] : LineType::from((string) $item['line_type']);
            $lineTotal = round((float) $item['line_total'], 2);
            $depositAmount = round((float) ($item['deposit_amount'] ?? 0), 2);

            if ($type === LineType::Sale) {
                $merchandise += $lineTotal;
            } else {
                $rental += $lineTotal;
                $deposit += $depositAmount;
            }
        }

        $discount = round((float) ($meta['discount_total'] ?? 0), 2);
        $shippingFee = round((float) ($meta['shipping_fee'] ?? 0), 2);
        $grand = round($merchandise + $rental + $deposit - $discount + $shippingFee, 2);

        return DB::transaction(function () use ($userId, $meta, $items, $merchandise, $rental, $deposit, $discount, $shippingFee, $grand) {
            $order = Order::query()->create([
                'user_id' => $userId,
                'channel' => $meta['channel'] ?? OrderChannel::Online,
                'shipping_method' => $meta['shipping_method'] ?? ShippingMethod::Pickup,
                'status' => OrderStatus::Pending,
                'merchandise_total' => round($merchandise, 2),
                'rental_total' => round($rental, 2),
                'deposit_total' => round($deposit, 2),
                'discount_total' => $discount,
                'shipping_fee' => $shippingFee,
                'grand_total' => $grand,
                'coupon_code' => $meta['coupon_code'] ?? null,
                'shipping_name' => $meta['shipping_name'] ?? null,
                'shipping_phone' => $meta['shipping_phone'] ?? null,
                'shipping_address' => $meta['shipping_address'] ?? null,
                'to_province_id' => $meta['to_province_id'] ?? null,
                'to_district_id' => $meta['to_district_id'] ?? null,
                'to_ward_code' => $meta['to_ward_code'] ?? null,
                'fulfillment_status' => $meta['fulfillment_status'] ?? FulfillmentStatus::Pickup,
                'notes' => $meta['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'line_type' => $item['line_type'],
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $item['product_name'],
                    'sku' => $item['sku'],
                    'image_url' => $item['image_url'] ?? null,
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'] ?? 1,
                    'rental_start' => $item['rental_start'] ?? null,
                    'rental_end' => $item['rental_end'] ?? null,
                    'deposit_amount' => $item['deposit_amount'] ?? 0,
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order->refresh()->load('items');
        });
    }

    public function changeStatus(Order $order, OrderStatus $next): Order
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Không chuyển được từ {$order->status->value} sang {$next->value}."],
            ]);
        }

        $order->status = $next;
        $order->save();

        return $order->refresh()->load('items');
    }

    public function confirm(Order $order): Order
    {
        return $this->changeStatus($order, OrderStatus::Confirmed);
    }

    public function markPaid(Order $order): Order
    {
        return $this->changeStatus($order, OrderStatus::Paid);
    }

    public function cancel(Order $order): Order
    {
        $order->loadMissing('items');

        if (! $order->canBeCancelled()) {
            $message = $order->hasBlockingRental()
                ? 'Đơn đã giao đồ thuê không hủy được. Dùng trả đồ hoặc hoàn tiền riêng.'
                : 'Không hủy được đơn ở trạng thái này.';

            throw ValidationException::withMessages([
                'status' => [$message],
            ]);
        }

        return $this->changeStatus($order, OrderStatus::Cancelled);
    }

    public function markProcessing(Order $order): Order
    {
        if ($order->status !== OrderStatus::Paid) {
            return $order->refresh()->load('items');
        }

        return $this->changeStatus($order, OrderStatus::Processing);
    }

    public function complete(Order $order): Order
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ hoàn tất đơn đã thanh toán hoặc đang xử lý.'],
            ]);
        }

        return $this->changeStatus($order, OrderStatus::Completed);
    }

    public function addCollectedRental(Order $order, float $amount): Order
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return $order;
        }

        $order->rental_total = round((float) $order->rental_total + $amount, 2);
        $order->grand_total = round((float) $order->grand_total + $amount, 2);
        $order->save();

        return $order->refresh()->load('items');
    }

    public function completeIfRentalsReturned(Order $order): Order
    {
        $order->loadMissing('bookings');
        if ($order->bookings->isEmpty()) {
            return $order;
        }

        $stillOpen = $order->bookings->contains(
            fn ($booking) => ! in_array($booking->status, [
                \App\Domain\Rental\Enums\BookingStatus::Returned,
                \App\Domain\Rental\Enums\BookingStatus::Cancelled,
            ], true),
        );

        if ($stillOpen || ! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)) {
            return $order;
        }

        return $this->changeStatus($order, OrderStatus::Completed);
    }
}
