<?php

namespace App\Domain\Inventory\Enums;

enum ItemStatus: string
{
    case Available = 'available';
    case Rented = 'rented';
    case Inspecting = 'inspecting';
    case Maintenance = 'maintenance';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Available => [self::Rented, self::Maintenance],
            self::Rented => [self::Inspecting],
            self::Inspecting => [self::Available, self::Maintenance],
            self::Maintenance => [self::Available],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Sẵn sàng',
            self::Rented => 'Đang thuê',
            self::Inspecting => 'Đang kiểm',
            self::Maintenance => 'Bảo trì',
        };
    }
}
