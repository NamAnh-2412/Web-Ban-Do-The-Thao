<?php

return [
    /*
     * MoMo thu một lần = tiền hàng/thuê − giảm + cọc + ship (nếu có), rồi phân bổ
     * thành các khoản Payment merchandise / deposit. Ship không ăn coupon.
     */
    'bank' => [
        'name' => env('PAYMENT_BANK_NAME', 'Vietcombank'),
        'account' => env('PAYMENT_BANK_ACCOUNT', '0123456789'),
        'holder' => env('PAYMENT_BANK_HOLDER', 'WEBTHETHAO'),
        'content_prefix' => env('PAYMENT_BANK_CONTENT_PREFIX', 'WTT'),
    ],
];
