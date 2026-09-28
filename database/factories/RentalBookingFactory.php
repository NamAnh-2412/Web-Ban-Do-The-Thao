<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalBooking> */
class RentalBookingFactory extends Factory
{
    protected $model = RentalBooking::class;

    public function definition(): array
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addDays(2);
        $daily = 50000.0;

        return [
            'user_id' => User::factory(),
            'order_id' => fn (array $attributes) => Order::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'inventory_item_id' => InventoryItem::factory(),
            'product_variant_id' => fn (array $attributes) => InventoryItem::query()
                ->find($attributes['inventory_item_id'])
                ->product_variant_id,
            'order_item_id' => function (array $attributes) use ($start, $end, $daily) {
                $variant = ProductVariant::query()->with('product')->find($attributes['product_variant_id']);

                return OrderItem::factory()->create([
                    'order_id' => $attributes['order_id'],
                    'line_type' => LineType::Rental,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product?->name ?? 'Thuê test',
                    'sku' => $variant->sku,
                    'unit_price' => $daily,
                    'quantity' => 1,
                    'rental_start' => $start->toDateString(),
                    'rental_end' => $end->toDateString(),
                    'deposit_amount' => 200000,
                    'line_total' => $daily * 3,
                ])->id;
            },
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'daily_rate' => $daily,
            'rental_amount' => $daily * 3,
            'deposit_amount' => 200000,
            'status' => BookingStatus::Pending,
        ];
    }
}
