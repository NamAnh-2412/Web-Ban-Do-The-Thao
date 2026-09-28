<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryStock> */
class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ];
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => 2,
            'low_stock_threshold' => 5,
        ]);
    }
}
