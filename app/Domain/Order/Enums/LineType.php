<?php

namespace App\Domain\Order\Enums;

enum LineType: string
{
    case Sale = 'sale';
    case Rental = 'rental';
}
