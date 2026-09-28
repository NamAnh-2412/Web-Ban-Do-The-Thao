<?php

namespace Database\Factories;

use App\Domain\Product\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sport> */
class SportFactory extends Factory
{
    protected $model = Sport::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Bóng đá', 'Tennis', 'Gym', 'Bóng rổ', 'Cầu lông', 'Bơi lội']).' '.fake()->unique()->numerify('##');

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
