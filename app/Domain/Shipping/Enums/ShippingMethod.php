<?php

namespace App\Domain\Shipping\Enums;

enum ShippingMethod: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Nhận tại quầy',
            self::Delivery => 'Giao nhà',
        };
    }
}
