<?php

namespace App\Domain\Coupon\Services;

use App\Domain\Coupon\Enums\CouponAppliesTo;
use App\Domain\Coupon\Enums\DiscountType;
use App\Domain\Coupon\Models\Coupon;
use App\Domain\Coupon\Models\CouponRedemption;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array{coupon: Coupon, discount: float, eligible: float}
     */
    public function quote(string $code, float $merchandise, float $rental): array
    {
        $coupon = $this->usable($code);
        $eligible = $this->eligibleAmount($coupon, $merchandise, $rental);

        if ($eligible + 0.0001 < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Đơn chưa đạt mức tối thiểu '.number_format((float) $coupon->min_order_amount, 0, ',', '.').'đ để dùng mã này.'],
            ]);
        }

        $discount = $coupon->discount_type === DiscountType::Percent
            ? round($eligible * ((float) $coupon->discount_value) / 100, 2)
            : round((float) $coupon->discount_value, 2);

        $discount = min($discount, $eligible);

        return [
            'coupon' => $coupon,
            'discount' => $discount,
            'eligible' => $eligible,
        ];
    }

    public function redeem(Coupon $coupon, Order $order, float $discount): void
    {
        DB::transaction(function () use ($coupon, $order, $discount) {
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $this->assertStillUsable($locked);

            CouponRedemption::query()->create([
                'coupon_id' => $locked->id,
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'discount_amount' => $discount,
            ]);

            $locked->increment('used_count');
        });
    }

    public function unredeem(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $rows = CouponRedemption::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $coupon = Coupon::query()->whereKey($row->coupon_id)->lockForUpdate()->first();
                if ($coupon !== null) {
                    $coupon->used_count = max(0, (int) $coupon->used_count - 1);
                    $coupon->save();
                }
                $row->delete();
            }
        });
    }

    public function usable(string $code): Coupon
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Mã giảm giá không tồn tại.'],
            ]);
        }

        $this->assertStillUsable($coupon);

        return $coupon;
    }

    private function assertStillUsable(Coupon $coupon): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Mã giảm giá đã tắt.'],
            ]);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Mã giảm giá chưa đến ngày dùng.'],
            ]);
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Mã giảm giá đã hết hạn.'],
            ]);
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Mã giảm giá đã hết lượt dùng.'],
            ]);
        }
    }

    private function eligibleAmount(Coupon $coupon, float $merchandise, float $rental): float
    {
        return match ($coupon->applies_to) {
            CouponAppliesTo::Sale => round($merchandise, 2),
            CouponAppliesTo::Rental => round($rental, 2),
            CouponAppliesTo::Both => round($merchandise + $rental, 2),
        };
    }
}
