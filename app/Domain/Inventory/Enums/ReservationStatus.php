<?php

namespace App\Domain\Inventory\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Committed = 'committed';
    case Released = 'released';
}
