<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'asset_code' => strtoupper(fake()->unique()->bothify('AST-####??')),
            'status' => ItemStatus::Available,
            'condition_note' => null,
        ];
    }
}
