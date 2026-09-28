<?php

namespace App\Domain\Coupon\Enums;

enum CouponAppliesTo: string
{
    case Sale = 'sale';
    case Rental = 'rental';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Bán',
            self::Rental => 'Thuê',
            self::Both => 'Bán và thuê',
        };
    }
}
