<?php

namespace App\Domain\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Completed => 'Đã thu',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
        };
    }
}
