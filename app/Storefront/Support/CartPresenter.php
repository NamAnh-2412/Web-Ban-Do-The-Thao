<?php

namespace App\Storefront\Support;

use App\Domain\Coupon\Services\CouponService;
use App\Gateway\Services\CheckoutOrchestrator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CartPresenter
{
    public function __construct(
        private CartService $cart,
        private CheckoutOrchestrator $checkout,
        private CouponService $coupons,
    ) {}

    /**
     * @return array{lines: list<array<string, mixed>>, merchandise_total: float, rental_total: float, deposit_total: float, grand_total: float}
     */
    public function viewData(): array
    {
        $merchandise = 0.0;
        $rental = 0.0;
        $deposit = 0.0;
        $viewLines = [];

        foreach ($this->cart->lines() as $raw) {
            $item = $this->hydrate($raw);
            $qty = max(1, (int) ($raw['quantity'] ?? 1));
            $isRental = ($raw['line_type'] ?? '') === 'rental';
            $lineTotal = is_array($item) ? (float) ($item['line_total'] ?? 0) : 0.0;
            $depositAmount = is_array($item) ? (float) ($item['deposit_amount'] ?? 0) : 0.0;
            if ($isRental) {
                $lineTotal *= $qty;
                $depositAmount *= $qty;
            }

            $viewLines[] = [
                'id' => $raw['id'],
                'line_type' => $raw['line_type'],
                'product_variant_id' => $raw['product_variant_id'],
                'quantity' => $qty,
                'rental_start' => $raw['rental_start'] ?? null,
                'rental_end' => $raw['rental_end'] ?? null,
                'product_name' => $item['product_name'] ?? 'Không còn bán/thuê',
                'sku' => $item['sku'] ?? '',
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'unit_price' => $item['unit_price'] ?? 0,
                'deposit_amount' => $depositAmount,
                'line_total' => $lineTotal,
                'available' => $item !== null,
            ];

            if ($item === null) {
                continue;
            }

            $type = $item['line_type']->value ?? (string) $item['line_type'];
            if ($type === 'sale') {
                $merchandise += $lineTotal;
            } else {
                $rental += $lineTotal;
                $deposit += $depositAmount;
            }
        }

        $discount = 0.0;
        $couponError = null;
        $code = trim((string) (old('coupon_code') ?? session('checkout_coupon', '')));
        if ($code !== '') {
            try {
                $discount = $this->coupons->quote($code, $merchandise, $rental)['discount'];
            } catch (ValidationException $e) {
                $couponError = $e->errors()['coupon_code'][0] ?? 'Mã giảm giá không hợp lệ.';
            }
        }

        $hasSale = collect($viewLines)->contains(fn (array $line) => ($line['line_type'] ?? '') === 'sale');
        $hasRent = collect($viewLines)->contains(fn (array $line) => ($line['line_type'] ?? '') === 'rental');
        $cartKind = match (true) {
            $hasSale && $hasRent => 'mixed',
            $hasRent => 'rental',
            $hasSale => 'sale',
            default => 'empty',
        };

        return [
            'lines' => $viewLines,
            'cart_kind' => $cartKind,
            'merchandise_total' => round($merchandise, 2),
            'rental_total' => round($rental, 2),
            'deposit_total' => round($deposit, 2),
            'discount_total' => $discount,
            'coupon_code' => $code,
            'coupon_error' => $couponError,
            'grand_total' => round($merchandise + $rental + $deposit - $discount, 2),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function checkoutLines(): array
    {
        $out = [];

        foreach ($this->cart->lines() as $line) {
            if (($line['line_type'] ?? '') === 'rental') {
                $copies = max(1, (int) ($line['quantity'] ?? 1));
                for ($i = 0; $i < $copies; $i++) {
                    $out[] = [
                        'line_type' => 'rental',
                        'product_variant_id' => $line['product_variant_id'],
                        'quantity' => 1,
                        'rental_start' => $line['rental_start'] ?? null,
                        'rental_end' => $line['rental_end'] ?? null,
                    ];
                }

                continue;
            }

            $out[] = [
                'line_type' => $line['line_type'],
                'product_variant_id' => $line['product_variant_id'],
                'quantity' => $line['quantity'] ?? 1,
                'rental_start' => $line['rental_start'] ?? null,
                'rental_end' => $line['rental_end'] ?? null,
            ];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $raw */
    private function hydrate(array $raw): ?array
    {
        try {
            $qty = max(1, (int) ($raw['quantity'] ?? 1));
            $prepared = $this->checkout->prepareLines([[
                'line_type' => $raw['line_type'],
                'product_variant_id' => $raw['product_variant_id'],
                'quantity' => ($raw['line_type'] ?? '') === 'rental' ? 1 : $qty,
                'rental_start' => $raw['rental_start'] ?? null,
                'rental_end' => $raw['rental_end'] ?? null,
            ]]);

            return $prepared[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
