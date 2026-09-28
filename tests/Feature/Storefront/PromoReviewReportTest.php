<?php

namespace Tests\Feature\Storefront;

use App\Domain\Coupon\Enums\CouponAppliesTo;
use App\Domain\Coupon\Enums\DiscountType;
use App\Domain\Coupon\Models\Coupon;
use App\Domain\Coupon\Models\CouponRedemption;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoReviewReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_reduces_goods_payment_and_is_redeemed(): void
    {
        $variant = $this->saleVariant();
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        Coupon::query()->create([
            'code' => 'SALE10',
            'name' => 'Giảm 10%',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'min_order_amount' => 0,
            'applies_to' => CouponAppliesTo::Both,
            'max_uses' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', [
            'shipping_name' => 'A',
            'shipping_phone' => '0901111222',
            'shipping_address' => '1 Đường Test',
            'payment_method' => 'bank_transfer',
            'coupon_code' => 'SALE10',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('SALE10', $order->coupon_code);
        $this->assertEquals(21900.0, (float) $order->discount_total);
        $this->assertEquals(197100.0, (float) $order->grand_total);

        $payment = Payment::query()->where('order_id', $order->id)->where('kind', 'merchandise')->first();
        $this->assertEquals(197100.0, (float) $payment->amount);
        $this->assertTrue(CouponRedemption::query()->where('order_id', $order->id)->exists());
        $this->assertSame(1, Coupon::query()->where('code', 'SALE10')->value('used_count'));
    }

    public function test_customer_reviews_after_paid_and_admin_sees_report(): void
    {
        $variant = $this->saleVariant();
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', [
            'shipping_name' => 'A',
            'shipping_phone' => '0901111222',
            'shipping_address' => '1 Đường Test',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $item = $order->items()->first();

        $this->post(route('orders.review', $order->id), [
            'order_item_id' => $item->id,
            'rating' => 5,
            'comment' => 'Tốt',
        ])->assertRedirect();
        $this->assertSame(0, Review::query()->count());

        $this->staffConfirmPendingPayments($order->id);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->actingAs($user);

        $this->post(route('orders.review', $order->id), [
            'order_item_id' => $item->id,
            'rating' => 5,
            'comment' => 'Giày êm',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $item->id,
            'rating' => 5,
            'comment' => 'Giày êm',
        ]);

        $this->get(route('catalog.show', $variant->product->slug))
            ->assertOk()
            ->assertSee('Giày êm');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Báo cáo')
            ->assertSee('Doanh thu');

        $this->actingAs($admin)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Giày êm');
    }

    public function test_admin_can_create_coupon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'he2026',
            'name' => 'Hè 2026',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'min_order_amount' => 100000,
            'applies_to' => 'sale',
            'is_active' => 1,
        ])->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'HE2026',
            'discount_type' => 'fixed',
        ]);
    }

    private function saleVariant(): ProductVariant
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo promo',
            'slug' => 'ao-promo-'.uniqid(),
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PROMO-'.uniqid(),
            'sale_price' => 219000,
            'rental_price_per_day' => null,
        ]);
    }
}
