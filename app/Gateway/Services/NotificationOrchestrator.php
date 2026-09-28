<?php

namespace App\Gateway\Services;

use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Services\NotificationWriter;
use App\Domain\Order\Models\Order;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalWriter;
use App\Domain\User\Models\User;
use App\Domain\User\Services\UserDirectory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class NotificationOrchestrator
{
    public function __construct(
        private NotificationWriter $notifications,
        private RentalWriter $rentals,
        private UserDirectory $users,
    ) {}

    public function notifyOrderConfirmed(User $user, Order $order): void
    {
        $order->loadMissing('items');
        $lines = $order->items->map(fn ($item) => sprintf(
            '- %s (%s) × %s — %sđ',
            $item->product_name,
            $item->sku,
            $item->quantity,
            number_format((float) $item->line_total, 0, ',', '.'),
        ))->implode("\n");

        $body = implode("\n", [
            "Xin chào {$user->name},",
            '',
            "Đơn #{$order->id} đã được ghi nhận (kênh {$order->channel->value}). Tồn kho đã khóa; cửa hàng sẽ xác nhận đơn. Tiền hàng và cọc là hai khoản thanh toán riêng.",
            'Hàng: '.number_format((float) $order->merchandise_total, 0, ',', '.').'đ',
            'Thuê: '.number_format((float) $order->rental_total, 0, ',', '.').'đ',
            'Cọc: '.number_format((float) $order->deposit_total, 0, ',', '.').'đ',
            'Tổng: '.number_format((float) $order->grand_total, 0, ',', '.').'đ',
            '',
            $lines,
            '',
            'WebTheThao',
        ]);

        $this->notifications->enqueueOnce(
            $user->id,
            $user->email,
            NotificationType::OrderConfirmed,
            "Đã nhận đơn #{$order->id} — chờ cửa hàng xác nhận",
            $body,
            [
                'dedupe_key' => 'order_confirmed:'.$order->id,
                'order_id' => $order->id,
            ],
        );
    }

    /**
     * @return array{due_reminders: int, overdue: int}
     */
    public function dispatchRentalCycle(?string $today = null): array
    {
        $today = Carbon::parse($today ?? now()->toDateString())->startOfDay();
        $dueDate = $today->copy()->addDays((int) config('notifications.due_reminder_days', 1))->toDateString();

        $due = $this->notifyBookings(
            $this->rentals->dueOn($dueDate),
            NotificationType::RentalDueReminder,
            fn (RentalBooking $booking, User $user) => [
                "Nhắc hạn trả đồ — lịch #{$booking->id}",
                implode("\n", [
                    "Xin chào {$user->name},",
                    '',
                    "Lịch thuê #{$booking->id} đến hạn trả vào {$booking->end_date->toDateString()}.",
                    'Vui lòng trả đúng hạn để tránh phí trễ và giữ tiền cọc.',
                    '',
                    'WebTheThao',
                ]),
                'rental_due:'.$booking->id.':'.$booking->end_date->toDateString(),
            ],
        );

        $this->rentals->markOverdue();

        $overdue = $this->notifyBookings(
            $this->rentals->listOverdue(),
            NotificationType::RentalOverdue,
            fn (RentalBooking $booking, User $user) => [
                "Quá hạn trả đồ — lịch #{$booking->id}",
                implode("\n", [
                    "Xin chào {$user->name},",
                    '',
                    "Lịch thuê #{$booking->id} đã quá hạn (ngày trả {$booking->end_date->toDateString()}).",
                    'Vui lòng liên hệ cửa hàng để trả đồ và xử lý phí phát sinh.',
                    '',
                    'WebTheThao',
                ]),
                'rental_overdue:'.$booking->id,
            ],
        );

        return [
            'due_reminders' => $due,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  Collection<int, RentalBooking>  $bookings
     * @param  callable(RentalBooking, User): array{0: string, 1: string, 2: string}  $compose
     */
    private function notifyBookings($bookings, NotificationType $type, callable $compose): int
    {
        $users = $this->users->findMany($bookings->pluck('user_id')->all());
        $created = 0;

        foreach ($bookings as $booking) {
            $user = $users->get($booking->user_id);
            if ($user === null || $user->email === '') {
                continue;
            }

            [$subject, $body, $key] = $compose($booking, $user);
            [, $isNew] = $this->notifications->enqueueOnce(
                $user->id,
                $user->email,
                $type,
                $subject,
                $body,
                [
                    'dedupe_key' => $key,
                    'booking_id' => $booking->id,
                    'order_id' => $booking->order_id,
                    'end_date' => $booking->end_date->toDateString(),
                ],
            );

            if ($isNew) {
                $created++;
            }
        }

        return $created;
    }

    public function notifyOrderConfirmedSafely(User $user, Order $order): void
    {
        try {
            $this->notifyOrderConfirmed($user, $order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
