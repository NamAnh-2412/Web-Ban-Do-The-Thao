<?php

namespace App\Domain\Rental\Enums;

enum ExtensionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
