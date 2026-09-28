<?php

namespace App\Domain\Payment\Enums;

enum GatewaySessionStatus: string
{
    case Pending = 'pending';
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
}
