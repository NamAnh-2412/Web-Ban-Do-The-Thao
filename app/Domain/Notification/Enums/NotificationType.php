<?php

namespace App\Domain\Notification\Enums;

enum NotificationType: string
{
    case OrderConfirmed = 'order_confirmed';
    case RentalDueReminder = 'rental_due_reminder';
    case RentalOverdue = 'rental_overdue';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Đã nhận đơn',
            self::RentalDueReminder => 'Nhắc hạn trả',
            self::RentalOverdue => 'Thuê quá hạn',
        };
    }
}
