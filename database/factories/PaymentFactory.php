<?php

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => fn (array $attributes) => Order::query()->find($attributes['order_id'])->user_id,
            'kind' => PaymentKind::Merchandise,
            'method' => PaymentMethod::Cash,
            'amount' => 100000,
            'status' => PaymentStatus::Pending,
            'note' => null,
        ];
    }
}
