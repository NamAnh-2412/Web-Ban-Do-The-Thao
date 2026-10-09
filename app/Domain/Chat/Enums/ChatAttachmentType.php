<?php

namespace App\Domain\Chat\Enums;

enum ChatAttachmentType: string
{
    case Product = 'product';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Sản phẩm',
            self::Order => 'Đơn hàng',
        };
    }
}
