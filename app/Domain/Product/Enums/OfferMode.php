<?php

namespace App\Domain\Product\Enums;

enum OfferMode: string
{
    case Sale = 'sale';
    case Rental = 'rental';
    case Both = 'both';

    /** @return list<string> */
    public static function matching(string $filter): array
    {
        return match ($filter) {
            self::Sale->value => [self::Sale->value, self::Both->value],
            self::Rental->value => [self::Rental->value, self::Both->value],
            self::Both->value => [self::Both->value],
            default => [self::Sale->value, self::Rental->value, self::Both->value],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Chỉ bán',
            self::Rental => 'Chỉ thuê',
            self::Both => 'Bán và thuê',
        };
    }
}
