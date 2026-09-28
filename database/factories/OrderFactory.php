<?php

namespace Database\Factories;

use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => OrderChannel::Online,
            'status' => OrderStatus::Confirmed,
            'merchandise_total' => 0,
            'rental_total' => 0,
            'deposit_total' => 0,
            'discount_total' => 0,
            'grand_total' => 0,
        ];
    }
}
