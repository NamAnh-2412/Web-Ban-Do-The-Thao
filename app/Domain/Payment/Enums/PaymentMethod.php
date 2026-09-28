<?php

namespace App\Domain\Payment\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Vnpay = 'vnpay';
    case Momo = 'momo';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
            self::BankTransfer => 'Chuyển khoản',
            self::Vnpay => 'VNPay',
            self::Momo => 'MoMo',
            self::Cod => 'COD (giao nhà)',
        };
    }

    /** @return list<self> */
    public static function onlineMethods(): array
    {
        return [self::BankTransfer, self::Momo, self::Cod];
    }
}
