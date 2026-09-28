<?php

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Completed, self::Cancelled],
            self::Processing => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function canCancelAfterPayment(): bool
    {
        return in_array($this, [self::Paid, self::Processing], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ chốt',
            self::Confirmed => 'Đã chốt',
            self::Paid => 'Đã thanh toán',
            self::Processing => 'Đang xử lý',
            self::Completed => 'Hoàn tất',
            self::Cancelled => 'Đã hủy',
        };
    }
}
