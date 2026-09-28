<?php

namespace App\Domain\User\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Khách',
            self::Staff => 'Cửa hàng (nhân viên)',
            self::Admin => 'Cửa hàng (quản trị)',
        };
    }
}
