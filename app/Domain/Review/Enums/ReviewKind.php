<?php

namespace App\Domain\Review\Enums;

enum ReviewKind: string
{
    case Sale = 'sale';
    case Rental = 'rental';
}
