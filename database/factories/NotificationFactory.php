<?php

namespace Database\Factories;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Models\Notification;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $orderId = fake()->numberBetween(1, 9_000_000);

        return [
            'user_id' => User::factory(),
            'email' => fn (array $attributes) => User::query()->find($attributes['user_id'])->email,
            'type' => NotificationType::OrderConfirmed,
            'channel' => NotificationChannel::Email,
            'subject' => 'Xác nhận đơn hàng',
            'body' => 'Đơn hàng đã được ghi nhận.',
            'payload' => [
                'dedupe_key' => 'order_confirmed:'.$orderId,
                'order_id' => $orderId,
            ],
            'status' => NotificationStatus::Queued,
        ];
    }
}
