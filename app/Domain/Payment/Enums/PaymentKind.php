<?php

namespace App\Domain\Payment\Enums;

enum PaymentKind: string
{
    case Merchandise = 'merchandise';
    case Deposit = 'deposit';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Merchandise => 'Tiền hàng',
            self::Deposit => 'Cọc',
            self::Refund => 'Hoàn tiền',
        };
    }
}
