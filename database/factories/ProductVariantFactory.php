<?php

namespace Database\Factories;

use App\Domain\Product\Enums\VariantCondition;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'size' => fake()->randomElement(['S', 'M', 'L', '41', '42']),
            'color' => fake()->randomElement(['Đen', 'Trắng', 'Xanh']),
            'condition' => VariantCondition::New,
            'sale_price' => fake()->randomFloat(2, 100000, 900000),
            'rental_price_per_day' => fake()->randomFloat(2, 20000, 80000),
            'rental_price_per_week' => fake()->randomFloat(2, 100000, 400000),
            'deposit_amount' => fake()->randomFloat(2, 50000, 200000),
            'is_active' => true,
        ];
    }
}
