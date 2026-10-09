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
use Illuminate\Support\Facades\Log;
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
     * Daily rental mail cycle: due reminders (N days ahead) then overdue notices.
     *
     * @return array{due_reminders: int, overdue: int}
     */
    public function dispatchRentalCycle(?string $today = null): array
    {
        $today = Carbon::parse($today ?? now()->toDateString())->startOfDay();
        $leadDays = max(0, (int) config('notifications.due_reminder_days', 1));
        $dueDate = $today->copy()->addDays($leadDays)->toDateString();

        Log::info('notification.rental_cycle.start', [
            'run_date' => $today->toDateString(),
            'due_target_date' => $dueDate,
            'lead_days' => $leadDays,
        ]);

        $due = $this->notifyDueReminders($today, $dueDate, $leadDays);

        $this->rentals->markOverdue();

        $overdue = $this->notifyOverdueBookings();

        Log::info('notification.rental_cycle.done', [
            'run_date' => $today->toDateString(),
            'due_reminders' => $due,
            'overdue' => $overdue,
        ]);

        return [
            'due_reminders' => $due,
            'overdue' => $overdue,
        ];
    }

    private function notifyDueReminders(Carbon $today, string $dueDate, int $leadDays): int
    {
        $bookings = $this->rentals->dueOn($dueDate);
        if ($bookings->isEmpty()) {
            return 0;
        }

        $users = $this->users->findMany($bookings->pluck('user_id')->all());
        $scheduleUrl = route('my-orders.index', ['tab' => 'thue']);
        $created = 0;

        /** @var Collection<string, Collection<int, RentalBooking>> $groups */
        $groups = $bookings->groupBy(
            fn (RentalBooking $booking) => $booking->user_id.'|'.$booking->order_id.'|'.$booking->end_date->toDateString()
        );

        foreach ($groups as $group) {
            /** @var RentalBooking $first */
            $first = $group->first();
            $user = $users->get($first->user_id);
            if ($user === null || $user->email === '') {
                Log::warning('notification.rental_due.skipped_missing_user', [
                    'booking_ids' => $group->pluck('id')->all(),
                    'user_id' => $first->user_id,
                ]);

                continue;
            }

            $items = $group->map(fn (RentalBooking $booking) => $this->bookingLine($booking))->values()->all();
            $itemLines = collect($items)->map(
                fn (array $item) => "- {$item['name']} (lịch #{$item['booking_id']})"
            )->implode("\n");

            $subject = count($items) === 1
                ? "Nhắc hạn trả đồ — lịch #{$first->id} (ngày {$dueDate})"
                : "Nhắc hạn trả đồ — đơn #{$first->order_id} (ngày {$dueDate})";

            $body = implode("\n", [
                "Xin chào {$user->name},",
                '',
                "Đơn thuê #{$first->order_id} đến hạn trả vào {$dueDate} (còn khoảng {$leadDays} ngày).",
                'Vui lòng trả đúng hạn để tránh phí trễ và giữ tiền cọc.',
                '',
                $itemLines,
                '',
                'Xem lịch thuê: '.$scheduleUrl,
                '',
                'WebTheThao',
            ]);

            $dedupeKey = 'rental_due:'.$first->user_id.':'.$first->order_id.':'.$dueDate;
            [, $isNew] = $this->notifications->enqueueOnce(
                $user->id,
                $user->email,
                NotificationType::RentalDueReminder,
                $subject,
                $body,
                [
                    'dedupe_key' => $dedupeKey,
                    'booking_ids' => $group->pluck('id')->values()->all(),
                    'order_id' => $first->order_id,
                    'end_date' => $dueDate,
                    'lead_days' => $leadDays,
                    'run_date' => $today->toDateString(),
                    'template' => 'emails.rental-due-reminder',
                    'template_data' => [
                        'customerName' => $user->name,
                        'orderId' => $first->order_id,
                        'endDate' => $dueDate,
                        'daysUntilDue' => $leadDays,
                        'items' => $items,
                        'scheduleUrl' => $scheduleUrl,
                    ],
                ],
            );

            if ($isNew) {
                $created++;
            }
        }

        return $created;
    }

    private function notifyOverdueBookings(): int
    {
        $bookings = $this->rentals->listOverdue();
        $users = $this->users->findMany($bookings->pluck('user_id')->all());
        $scheduleUrl = route('my-orders.index', ['tab' => 'thue']);
        $created = 0;

        foreach ($bookings as $booking) {
            $user = $users->get($booking->user_id);
            if ($user === null || $user->email === '') {
                continue;
            }

            $line = $this->bookingLine($booking);
            $endDate = $booking->end_date->toDateString();
            $subject = "Quá hạn trả đồ — lịch #{$booking->id}";
            $body = implode("\n", [
                "Xin chào {$user->name},",
                '',
                "Lịch thuê #{$booking->id} đã quá hạn (ngày trả {$endDate}).",
                "Món: {$line['name']}",
                'Vui lòng liên hệ cửa hàng để trả đồ và xử lý phí phát sinh.',
                '',
                'Xem lịch thuê: '.$scheduleUrl,
                '',
                'WebTheThao',
            ]);

            [, $isNew] = $this->notifications->enqueueOnce(
                $user->id,
                $user->email,
                NotificationType::RentalOverdue,
                $subject,
                $body,
                [
                    'dedupe_key' => 'rental_overdue:'.$booking->id,
                    'booking_id' => $booking->id,
                    'booking_ids' => [$booking->id],
                    'order_id' => $booking->order_id,
                    'end_date' => $endDate,
                    'template' => 'emails.rental-overdue',
                    'template_data' => [
                        'customerName' => $user->name,
                        'bookingId' => $booking->id,
                        'orderId' => $booking->order_id,
                        'endDate' => $endDate,
                        'productName' => $line['name'],
                        'sku' => $line['sku'],
                        'scheduleUrl' => $scheduleUrl,
                    ],
                ],
            );

            if ($isNew) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array{booking_id: int, name: string, sku: string, start_date: string, end_date: string}
     */
    private function bookingLine(RentalBooking $booking): array
    {
        $name = $booking->orderItem?->product_name
            ?? $booking->variant?->product?->name
            ?? ('Lịch thuê #'.$booking->id);
        $sku = (string) ($booking->orderItem?->sku ?? $booking->variant?->sku ?? '');

        return [
            'booking_id' => $booking->id,
            'name' => $name,
            'sku' => $sku,
            'start_date' => $booking->start_date->toDateString(),
            'end_date' => $booking->end_date->toDateString(),
        ];
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
