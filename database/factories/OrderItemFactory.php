<?php

namespace Database\Factories;

use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'line_type' => LineType::Sale,
            'product_id' => Product::factory(),
            'product_variant_id' => fn (array $attributes) => ProductVariant::factory()->create([
                'product_id' => $attributes['product_id'],
            ])->id,
            'product_name' => fn (array $attributes) => Product::query()->find($attributes['product_id'])?->name ?? 'Sản phẩm test',
            'sku' => fn (array $attributes) => ProductVariant::query()->find($attributes['product_variant_id'])?->sku ?? fake()->unique()->bothify('SKU-####'),
            'unit_price' => 100000,
            'quantity' => 1,
            'deposit_amount' => 0,
            'line_total' => 100000,
        ];
    }
}
