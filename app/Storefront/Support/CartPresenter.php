<?php

namespace App\Storefront\Support;

use App\Domain\Coupon\Services\CouponService;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Gateway\Services\CheckoutOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        $rawLines = $this->cart->lines();
        $variantIds = collect($rawLines)->pluck('product_variant_id')->map(fn ($id) => (int) $id)->unique()->values();
        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->with(['product.variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        foreach ($rawLines as $raw) {
            $item = $this->hydrate($raw);
            $qty = max(1, (int) ($raw['quantity'] ?? 1));
            $isRental = ($raw['line_type'] ?? '') === 'rental';
            $lineTotal = is_array($item) ? (float) ($item['line_total'] ?? 0) : 0.0;
            $depositAmount = is_array($item) ? (float) ($item['deposit_amount'] ?? 0) : 0.0;
            if ($isRental) {
                $lineTotal *= $qty;
                $depositAmount *= $qty;
            }

            $variant = $variants->get((int) ($raw['product_variant_id'] ?? 0));
            $product = $variant?->product;

            $viewLines[] = [
                'id' => $raw['id'],
                'line_type' => $raw['line_type'],
                'product_variant_id' => $raw['product_variant_id'],
                'product_id' => $product?->id,
                'product_slug' => $product?->slug,
                'quantity' => $qty,
                'rental_start' => $raw['rental_start'] ?? null,
                'rental_end' => $raw['rental_end'] ?? null,
                'rental_days' => $this->rentalDays($raw['rental_start'] ?? null, $raw['rental_end'] ?? null),
                'product_name' => $item['product_name'] ?? ($product?->name ?? 'Không còn bán/thuê'),
                'sku' => $item['sku'] ?? ($variant?->sku ?? ''),
                'size' => $item['size'] ?? $variant?->size,
                'color' => $item['color'] ?? $variant?->color,
                'image_src' => $product?->imageSrc(),
                'unit_price' => $item['unit_price'] ?? 0,
                'deposit_amount' => $depositAmount,
                'line_total' => $lineTotal,
                'available' => $item !== null,
                'variants' => $this->variantOptions($product, (string) ($raw['line_type'] ?? '')),
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

    /** @return list<array{id: int, label: string, size: ?string, color: ?string}> */
    private function variantOptions(?Product $product, string $lineType): array
    {
        if ($product === null) {
            return [];
        }

        return $product->variants
            ->filter(function (ProductVariant $variant) use ($lineType, $product) {
                if (! $variant->is_active) {
                    return false;
                }
                $mode = $product->offer_mode;
                if ($lineType === 'sale') {
                    return in_array($mode, [OfferMode::Sale, OfferMode::Both], true)
                        && $variant->sale_price !== null;
                }

                return in_array($mode, [OfferMode::Rental, OfferMode::Both], true)
                    && $variant->rental_price_per_day !== null;
            })
            ->map(function (ProductVariant $variant) {
                $parts = array_filter([
                    $variant->size ? 'Size '.$variant->size : null,
                    $variant->color,
                    $variant->sku,
                ]);

                return [
                    'id' => $variant->id,
                    'label' => implode(' · ', $parts) ?: ('Biến thể #'.$variant->id),
                    'size' => $variant->size,
                    'color' => $variant->color,
                ];
            })
            ->values()
            ->all();
    }

    private function rentalDays(mixed $start, mixed $end): ?int
    {
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return null;
        }

        try {
            return Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;
        } catch (Throwable) {
            return null;
        }
    }
}
