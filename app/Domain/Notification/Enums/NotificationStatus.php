<?php

namespace App\Domain\Notification\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Chờ gửi',
            self::Sent => 'Đã gửi',
            self::Failed => 'Gửi lỗi',
        };
    }
}
