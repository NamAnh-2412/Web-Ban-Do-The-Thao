<?php

namespace App\Domain\Rental\Enums;

enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Nguyên vẹn',
            self::Damaged => 'Hư hỏng',
            self::Lost => 'Mất',
        };
    }
}
