<?php

namespace App\Gateway\Services;

use App\Domain\Coupon\Services\CouponService;
use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Inventory\Services\InventoryWriter;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderWriter;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Services\PaymentWriter;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Services\CatalogService;
use App\Domain\Rental\Services\RentalPricer;
use App\Domain\Rental\Services\RentalWriter;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\Shipping\Enums\ShippingMethod;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutOrchestrator
{
    public function __construct(
        private CatalogService $catalog,
        private AvailabilityService $availability,
        private InventoryWriter $inventory,
        private RentalPricer $pricer,
        private RentalWriter $rentals,
        private OrderWriter $orders,
        private PaymentWriter $payments,
        private NotificationOrchestrator $notifications,
        private CouponService $coupons,
        private ShippingService $shipping,
    ) {}

    /**
     * Online: $actor là khách (chủ đơn). POS: $actor là cửa hàng, chủ đơn lấy từ payload customer_id.
     *
     * @param  array<string, mixed>  $payload
     */
    public function checkout(User $actor, array $payload): Order
    {
        $channel = OrderChannel::from($payload['channel'] ?? OrderChannel::Online->value);
        $customer = $this->resolveCustomer($actor, $channel, $payload);

        $prepared = $this->prepareLines($payload['lines'] ?? []);
        $merchandise = 0.0;
        $rental = 0.0;
        foreach ($prepared as $line) {
            $type = $line['line_type'] instanceof LineType ? $line['line_type'] : LineType::from((string) $line['line_type']);
            if ($type === LineType::Sale) {
                $merchandise += (float) $line['line_total'];
            } else {
                $rental += (float) $line['line_total'];
            }
        }

        $couponCode = trim((string) ($payload['coupon_code'] ?? ''));
        $discount = 0.0;
        $coupon = null;
        if ($couponCode !== '') {
            $quoted = $this->coupons->quote($couponCode, $merchandise, $rental);
            $coupon = $quoted['coupon'];
            $discount = $quoted['discount'];
        }

        $quoted = $this->quoteShipping($channel, $payload);

        $order = null;

        try {
            $order = $this->orders->create($customer->id, [
                'channel' => $channel,
                'shipping_method' => $quoted['shipping_method']->value,
                'shipping_name' => $payload['shipping_name'] ?? $customer->name,
                'shipping_phone' => $payload['shipping_phone'] ?? $customer->phone,
                'shipping_address' => $quoted['shipping_address'],
                'to_province_id' => $quoted['to_province_id'],
                'to_district_id' => $quoted['to_district_id'],
                'to_ward_code' => $quoted['to_ward_code'],
                'shipping_fee' => $quoted['shipping_fee'],
                'fulfillment_status' => $quoted['fulfillment_status']->value,
                'notes' => $payload['notes'] ?? null,
                'coupon_code' => $coupon?->code,
                'discount_total' => $discount,
            ], $prepared);

            $this->lockAndBook($customer->id, $order);

            $method = $this->paymentMethodForChannel($channel, $payload['payment_method'] ?? null, $quoted['shipping_method']);
            $goods = max(0, round(
                (float) $order->merchandise_total
                + (float) $order->rental_total
                - (float) $order->discount_total
                + (float) $order->shipping_fee,
                2
            ));
            $this->payments->createIntents(
                $order->id,
                $customer->id,
                $goods,
                (float) $order->deposit_total,
                $method,
            );

            if ($coupon !== null && $discount > 0) {
                $this->coupons->redeem($coupon, $order, $discount);
            }

            $this->notifications->notifyOrderConfirmedSafely($customer, $order);

            return $order->load('items');
        } catch (Throwable $e) {
            if ($order !== null) {
                $this->compensate($order);
            }

            throw $e;
        }
    }

    public function confirmByShop(Order $order): Order
    {
        if ($order->status === OrderStatus::Pending) {
            $order = $this->orders->confirm($order);
        }

        return $order;
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

        return DB::transaction(function () use ($order) {
            $this->payments->cancelPendingByOrder($order->id);
            $this->payments->refundCompletedCollections($order->id, $order->user_id, 'Hoàn tiền khi hủy đơn.');
            $this->inventory->releaseByOrder($order->id);
            $this->rentals->cancelByOrder($order->id);
            $this->coupons->unredeem($order);
            $this->shipping->cancelShipment($order);

            return $this->orders->cancel($order);
        });
    }

    public function markPaid(Order $order): Order
    {
        $this->inventory->commitByOrder($order->id);
        $this->rentals->confirmByOrder($order->id);
        $order = $this->orders->markPaid($order);

        if ($order->isDelivery() && ! $order->ghn_order_code) {
            $this->shipping->createShipment($order, true);
            $order->refresh();
        }

        if ($order->channel === OrderChannel::Pos) {
            $this->rentals->activateByOrder($order->id);
            $order->load('bookings');
            if ($order->bookings->isNotEmpty()) {
                $order = $this->orders->markProcessing($order);
            }
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCustomer(User $actor, OrderChannel $channel, array $payload): User
    {
        if ($channel === OrderChannel::Online) {
            if ($actor->isStoreAccount()) {
                throw ValidationException::withMessages([
                    'channel' => ['Tài khoản cửa hàng không mua trên website. Dùng khu Quản trị để xử lý đơn.'],
                ]);
            }

            return $actor;
        }

        if (! $actor->isStoreAccount()) {
            throw ValidationException::withMessages([
                'channel' => ['Chỉ nhân viên cửa hàng mới tạo đơn tại quầy.'],
            ]);
        }

        $customer = User::query()->find((int) ($payload['customer_id'] ?? 0));
        if ($customer === null || ! $customer->isCustomer()) {
            throw ValidationException::withMessages([
                'customer_id' => ['Chọn khách hàng hợp lệ.'],
            ]);
        }

        return $customer;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function prepareLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => ['Giỏ hàng trống.'],
            ]);
        }

        $items = [];

        foreach ($lines as $index => $line) {
            $type = LineType::from((string) ($line['line_type'] ?? ''));
            $variantId = (int) ($line['product_variant_id'] ?? 0);
            $variant = $this->catalog->variantForCheckout($variantId);
            $product = $variant->product;
            $mode = $product->offer_mode;

            if ($type === LineType::Sale) {
                if (! in_array($mode, [OfferMode::Sale, OfferMode::Both], true) || $variant->sale_price === null) {
                    throw ValidationException::withMessages([
                        "lines.$index.line_type" => ['Variant này không bán.'],
                    ]);
                }

                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $stock = $this->availability->saleAvailability($variant->id);
                if ($stock['quantity_available'] < $qty) {
                    throw ValidationException::withMessages([
                        "lines.$index.quantity" => ['Không đủ tồn kho bán.'],
                    ]);
                }

                $unit = round((float) $variant->sale_price, 2);
                $items[] = [
                    'line_type' => LineType::Sale,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'sku' => $variant->sku,
                    'image_url' => $product->image_url,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'unit_price' => $unit,
                    'quantity' => $qty,
                    'rental_start' => null,
                    'rental_end' => null,
                    'deposit_amount' => 0,
                    'line_total' => round($unit * $qty, 2),
                ];

                continue;
            }

            if (! in_array($mode, [OfferMode::Rental, OfferMode::Both], true) || $variant->rental_price_per_day === null) {
                throw ValidationException::withMessages([
                    "lines.$index.line_type" => ['Variant này không cho thuê.'],
                ]);
            }

            $start = (string) ($line['rental_start'] ?? '');
            $end = (string) ($line['rental_end'] ?? '');
            $quote = $this->pricer->quote(
                $start,
                $end,
                (float) $variant->rental_price_per_day,
                $variant->rental_price_per_week !== null ? (float) $variant->rental_price_per_week : null,
                (float) ($variant->deposit_amount ?? 0),
            );

            $free = $this->availability->rentalAvailability($variant->id, $quote['start_date'], $quote['end_date']);
            if (! $free['available']) {
                throw ValidationException::withMessages([
                    "lines.$index.rental_start" => ['Không còn món thuê trống trong khoảng ngày này.'],
                ]);
            }

            $items[] = [
                'line_type' => LineType::Rental,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'product_name' => $product->name,
                'sku' => $variant->sku,
                'image_url' => $product->image_url,
                'size' => $variant->size,
                'color' => $variant->color,
                'unit_price' => $quote['daily_rate'],
                'quantity' => 1,
                'rental_start' => $quote['start_date'],
                'rental_end' => $quote['end_date'],
                'deposit_amount' => $quote['deposit_amount'],
                'line_total' => $quote['rental_amount'],
                'weekly_rate' => $quote['weekly_rate'],
            ];
        }

        return $items;
    }

    private function lockAndBook(int $userId, Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->line_type === LineType::Sale) {
                $this->inventory->reserveSale(
                    $item->product_variant_id,
                    $item->quantity,
                    $order->id,
                );

                continue;
            }

            $reservation = $this->inventory->reserveRental(
                $item->product_variant_id,
                $item->rental_start->toDateString(),
                $item->rental_end->toDateString(),
                $order->id,
            );

            $variant = $this->catalog->variantForCheckout($item->product_variant_id);

            $this->rentals->createBooking($userId, [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'inventory_item_id' => $reservation->inventory_item_id,
                'product_variant_id' => $item->product_variant_id,
                'start_date' => $item->rental_start->toDateString(),
                'end_date' => $item->rental_end->toDateString(),
                'daily_rate' => $item->unit_price,
                'weekly_rate' => $variant->rental_price_per_week,
                'deposit_amount' => $item->deposit_amount,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     shipping_method: ShippingMethod,
     *     shipping_address: string,
     *     to_province_id: int|null,
     *     to_district_id: int|null,
     *     to_ward_code: string|null,
     *     shipping_fee: float,
     *     fulfillment_status: FulfillmentStatus
     * }
     */
    private function quoteShipping(OrderChannel $channel, array $payload): array
    {
        if ($channel === OrderChannel::Pos) {
            return [
                'shipping_method' => ShippingMethod::Pickup,
                'shipping_address' => (string) ($payload['shipping_address'] ?? 'Nhận tại quầy'),
                'to_province_id' => null,
                'to_district_id' => null,
                'to_ward_code' => null,
                'shipping_fee' => 0.0,
                'fulfillment_status' => FulfillmentStatus::Pickup,
            ];
        }

        $method = ShippingMethod::tryFrom((string) ($payload['shipping_method'] ?? ShippingMethod::Pickup->value))
            ?? ShippingMethod::Pickup;

        if ($method === ShippingMethod::Pickup) {
            $address = trim((string) ($payload['shipping_address'] ?? ''));

            return [
                'shipping_method' => $method,
                'shipping_address' => $address !== '' ? $address : 'Nhận tại quầy WebTheThao',
                'to_province_id' => null,
                'to_district_id' => null,
                'to_ward_code' => null,
                'shipping_fee' => 0.0,
                'fulfillment_status' => FulfillmentStatus::Pickup,
            ];
        }

        $districtId = (int) ($payload['to_district_id'] ?? 0);
        $wardCode = trim((string) ($payload['to_ward_code'] ?? ''));
        $address = trim((string) ($payload['shipping_address'] ?? ''));

        if ($districtId <= 0 || $wardCode === '' || $address === '') {
            throw ValidationException::withMessages([
                'shipping_address' => ['Giao nhà cần địa chỉ, quận và phường GHN.'],
            ]);
        }

        $itemCount = max(1, count($payload['lines'] ?? []));
        $fee = $this->shipping->quoteFee($districtId, $wardCode, $itemCount);

        return [
            'shipping_method' => $method,
            'shipping_address' => $address,
            'to_province_id' => isset($payload['to_province_id']) ? (int) $payload['to_province_id'] : null,
            'to_district_id' => $districtId,
            'to_ward_code' => $wardCode,
            'shipping_fee' => (float) $fee,
            'fulfillment_status' => FulfillmentStatus::Pending,
        ];
    }

    private function paymentMethodForChannel(OrderChannel $channel, mixed $raw, ShippingMethod $shipping): PaymentMethod
    {
        $fallback = $channel === OrderChannel::Pos ? PaymentMethod::Cash : PaymentMethod::BankTransfer;
        $method = is_string($raw) && $raw !== ''
            ? PaymentMethod::from($raw)
            : $fallback;

        if ($channel === OrderChannel::Online) {
            if (! in_array($method, PaymentMethod::onlineMethods(), true)) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Đơn trên website nhận chuyển khoản, MoMo hoặc COD giao nhà.'],
                ]);
            }

            if ($method === PaymentMethod::Cod && $shipping !== ShippingMethod::Delivery) {
                throw ValidationException::withMessages([
                    'payment_method' => ['COD chỉ dùng khi giao nhà. Nhận tại quầy dùng chuyển khoản hoặc MoMo.'],
                ]);
            }

            return $method;
        }

        if (! in_array($method, [PaymentMethod::Cash, PaymentMethod::BankTransfer], true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Quầy chỉ nhận tiền mặt hoặc chuyển khoản.'],
            ]);
        }

        return $method;
    }

    private function compensate(Order $order): void
    {
        $this->inventory->releaseByOrder($order->id);
        $this->rentals->cancelByOrder($order->id);
        $this->payments->cancelPendingByOrder($order->id);

        if ($order->status->canCancel()) {
            try {
                $this->orders->cancel($order);
            } catch (ValidationException) {
                // Order đã hủy hoặc không hủy được — saga vẫn nhả kho.
            }
        }
    }
}
