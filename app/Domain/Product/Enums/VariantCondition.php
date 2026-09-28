<?php

namespace App\Domain\Product\Enums;

enum VariantCondition: string
{
    case New = 'new';
    case Used = 'used';
}
