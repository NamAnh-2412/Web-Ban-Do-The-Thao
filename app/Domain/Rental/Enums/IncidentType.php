<?php

namespace App\Domain\Rental\Enums;

enum IncidentType: string
{
    case Damage = 'damage';
    case Late = 'late';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Hư hỏng',
            self::Late => 'Trả muộn',
            self::Lost => 'Mất đồ',
        };
    }
}
