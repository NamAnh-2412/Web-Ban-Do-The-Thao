<?php

namespace Database\Factories;

use App\Domain\Product\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Dụng cụ', 'Quần áo', 'Giày', 'Phụ kiện']).' '.fake()->unique()->numerify('##');

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
