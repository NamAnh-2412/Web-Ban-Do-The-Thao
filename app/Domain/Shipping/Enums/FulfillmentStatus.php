<?php

namespace App\Domain\Shipping\Enums;

enum FulfillmentStatus: string
{
    case Pickup = 'pickup';
    case Pending = 'pending';
    case ReadyToPick = 'ready_to_pick';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Nhận tại quầy',
            self::Pending => 'Chờ tạo vận đơn',
            self::ReadyToPick => 'Đã đóng gói / chờ lấy',
            self::Delivering => 'Đang giao',
            self::Delivered => 'Đã giao',
            self::Cancelled => 'Đã hủy vận đơn',
            self::Failed => 'Lỗi vận đơn',
        };
    }

    public function canCancelOnGhn(): bool
    {
        return in_array($this, [self::Pending, self::ReadyToPick], true);
    }
}
