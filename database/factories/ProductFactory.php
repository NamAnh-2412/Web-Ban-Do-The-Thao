<?php

namespace Database\Factories;

use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true).' '.fake()->unique()->numerify('###');

        return [
            'category_id' => Category::factory(),
            'sport_id' => Sport::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'offer_mode' => OfferMode::Both,
            'image_url' => '/media/images/demo.jpg',
            'video_url' => null,
            'is_active' => true,
        ];
    }
}
