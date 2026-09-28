<?php

namespace App\Domain\Rental\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Returned = 'returned';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Active, self::Cancelled],
            self::Active => [self::Returned, self::Overdue],
            self::Overdue => [self::Returned],
            self::Returned, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Active, self::Overdue], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Active => 'Đang thuê',
            self::Returned => 'Đã trả',
            self::Overdue => 'Quá hạn',
            self::Cancelled => 'Đã hủy',
        };
    }
}
