<?php

namespace Tests\Feature\Admin;

use App\Domain\Coupon\Enums\CouponAppliesTo;
use App\Domain\Coupon\Enums\DiscountType;
use App\Domain\Coupon\Models\Coupon;
use App\Domain\Coupon\Models\CouponRedemption;
use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Models\InventoryStockReservation;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Enums\ExtensionStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Models\RentalExtension;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseoutFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_timezone_is_vietnam(): void
    {
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('Asia/Ho_Chi_Minh', now()->timezoneName);
    }

    public function test_paid_mixed_order_can_be_cancelled_before_handover(): void
    {
        [$saleVariant, $rentVariant, $item] = $this->seedMixed();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($customer);
        $this->addMixedCart($saleVariant->id, $rentVariant->id);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->payAll($customer, $order);
        $this->actingAs($staff)->post(route('admin.orders.mark-paid', $order))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->status->value);

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hủy đơn')
            ->assertDontSee('Hoàn tiền');

        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(BookingStatus::Cancelled, RentalBooking::query()->where('order_id', $order->id)->first()->status);
        $this->assertSame(ItemStatus::Available, $item->fresh()->status);
        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(10, $stock->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);
        $this->assertTrue(Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Refund)->exists());
    }

    public function test_cancelling_order_unredeems_coupon_so_it_can_be_reused(): void
    {
        $variant = $this->saleVariant(5);
        Coupon::query()->create([
            'code' => 'ONCE',
            'name' => 'Một lần',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'min_order_amount' => 0,
            'applies_to' => CouponAppliesTo::Both,
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload(['coupon_code' => 'ONCE']))->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->assertSame(1, (int) Coupon::query()->where('code', 'ONCE')->value('used_count'));
        $this->assertTrue(CouponRedemption::query()->where('order_id', $order->id)->exists());

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.orders.cancel', $order))->assertRedirect();

        $this->assertSame(0, (int) Coupon::query()->where('code', 'ONCE')->value('used_count'));
        $this->assertFalse(CouponRedemption::query()->where('order_id', $order->id)->exists());

        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload(['coupon_code' => 'ONCE']))->assertRedirect();

        $this->assertSame(1, (int) Coupon::query()->where('code', 'ONCE')->value('used_count'));
        $this->assertSame(2, Order::query()->where('user_id', $customer->id)->count());
    }

    public function test_staff_refunds_full_deposit_after_on_time_return(): void
    {
        [$variant] = $this->seedRental();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking))->assertRedirect();
        $this->travelTo('2026-09-03 10:00:00');
        $this->actingAs($staff)->post(route('admin.rentals.return', $booking), ['condition' => 'good'])->assertRedirect();
        $this->travelBack();

        $this->actingAs($staff)
            ->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Hoàn cọc');

        $this->post(route('admin.rentals.deposit-refund', $booking))->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $booking->order_id,
            'kind' => PaymentKind::Refund->value,
            'amount' => 300000,
            'note' => 'Hoàn cọc lịch thuê #'.$booking->id,
        ]);

        $this->from(route('admin.rentals.show', $booking))
            ->post(route('admin.rentals.deposit-refund', $booking))
            ->assertRedirect(route('admin.rentals.show', $booking))
            ->assertSessionHasErrors('amount');

        $this->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Đã hoàn cọc buổi này');
    }

    public function test_staff_refunds_suggested_deposit_after_late_return(): void
    {
        [$variant] = $this->seedRental();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking));
        $this->travelTo('2026-09-05 10:00:00');
        $this->actingAs($staff)->post(route('admin.rentals.return', $booking), ['condition' => 'good'])->assertRedirect();
        $this->travelBack();

        $this->actingAs($staff)->post(route('admin.rentals.deposit-refund', $booking))->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'order_id' => $booking->order_id,
            'kind' => PaymentKind::Refund->value,
            'amount' => 140000,
        ]);
    }

    public function test_online_mark_paid_fails_when_deposit_still_pending(): void
    {
        [, $rentVariant] = $this->seedMixed();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($customer);
        $this->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $rentVariant->id,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $merchandise = Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Merchandise)->first();
        $this->actingAs($staff)->post(route('admin.payments.confirm', $merchandise))->assertRedirect();

        $this->actingAs($staff)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.mark-paid', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $order->fresh()->status->value);
        $deposit = Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Deposit)->first();
        $this->assertSame(PaymentStatus::Pending, $deposit->status);
        $this->assertSame(PaymentStatus::Completed, $merchandise->fresh()->status);
    }

    public function test_release_expired_cancels_online_pending_but_keeps_pos_save(): void
    {
        $onlineVariant = $this->saleVariant(5);
        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $onlineVariant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();
        $online = Order::query()->where('user_id', $customer->id)->first();

        $posVariant = $this->saleVariant(5);
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $posVariant->id,
            'quantity' => 1,
        ]);
        $this->post(route('admin.pos.store'), [
            'customer_mode' => 'walkin',
            'walkin_name' => 'Khách POS giữ',
            'action' => 'save',
        ])->assertRedirect();
        $pos = Order::query()->where('channel', 'pos')->latest('id')->first();

        InventoryStockReservation::query()->where('order_id', $online->id)->update([
            'expires_at' => now()->subMinutes(20),
        ]);
        InventoryStockReservation::query()->where('order_id', $pos->id)->update([
            'expires_at' => now()->subMinutes(20),
        ]);

        $this->artisan('webthethao:release-expired')->assertSuccessful();

        $this->assertSame('cancelled', $online->fresh()->status->value);
        $onlineStock = InventoryStock::query()->where('product_variant_id', $onlineVariant->id)->first();
        $this->assertSame(0, $onlineStock->quantity_reserved);
        $this->assertSame(ReservationStatus::Released, InventoryStockReservation::query()->where('order_id', $online->id)->first()->status);

        $this->assertSame('pending', $pos->fresh()->status->value);
        $posStock = InventoryStock::query()->where('product_variant_id', $posVariant->id)->first();
        $this->assertSame(1, $posStock->quantity_reserved);
    }

    public function test_selling_both_sku_does_not_reduce_rental_items(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $sku = 'BOTH-CLOSE-'.uniqid();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Áo vừa bán vừa thuê',
            'category_id' => $category->id,
            'offer_mode' => 'both',
            'variants' => [[
                'sku' => $sku,
                'size' => 'M',
                'color' => 'Đỏ',
                'sale_price' => 100000,
                'rental_price_per_day' => 40000,
                'deposit_amount' => 80000,
                'quantity' => 10,
                'rental_quantity' => 10,
            ]],
        ])->assertRedirect();

        $variant = ProductVariant::query()->where('sku', $sku)->first();
        $this->assertSame(10, $variant->stock->quantity_on_hand);
        $this->assertSame(10, $variant->items()->count());

        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();
        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->payAll($customer, $order);
        $this->actingAs($admin)->post(route('admin.orders.mark-paid', $order))->assertRedirect();

        $this->assertSame(7, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(10, $variant->items()->count());
        $this->assertSame(10, $variant->items()->where('status', ItemStatus::Available)->count());

        $html = $this->actingAs($admin)->get(route('admin.inventory.index'))->assertOk()->getContent();
        $this->assertStringContainsString('hai nhóm độc lập', $html);
    }

    public function test_staff_collects_extension_then_end_date_and_extra_payment_change(): void
    {
        [$variant] = $this->seedRental();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($customer)
            ->from(route('orders.show', $booking->order_id))
            ->post(route('orders.extensions.store', $booking->order_id), [
                'booking_id' => $booking->id,
                'new_end_date' => '2026-09-06',
            ])
            ->assertRedirect();

        $extension = RentalExtension::query()->where('rental_booking_id', $booking->id)->first();
        $this->assertNotNull($extension);
        $this->assertSame(ExtensionStatus::Pending, $extension->status);
        $this->assertEquals(240000, (float) $extension->extra_amount);

        $this->actingAs($staff)
            ->post(route('admin.rentals.extensions.collect', [$booking, $extension]))
            ->assertRedirect();

        $booking = $booking->fresh();
        $this->assertSame('2026-09-06', $booking->end_date->toDateString());
        $this->assertEquals(480000, (float) $booking->rental_amount);
        $this->assertSame(ExtensionStatus::Approved, $extension->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $booking->order_id,
            'kind' => PaymentKind::Merchandise->value,
            'amount' => 240000,
            'status' => PaymentStatus::Completed->value,
            'note' => 'Thu thêm gia hạn lịch #'.$booking->id,
        ]);
        $order = $booking->order->fresh();
        $this->assertSame('paid', $order->status->value);
        $this->assertEquals(480000, (float) $order->rental_total);
        $this->assertEquals(780000, (float) $order->grand_total);
    }

    public function test_sale_only_complete_locks_cancel(): void
    {
        $variant = $this->saleVariant(5);
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();
        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->payAll($customer, $order);
        $this->actingAs($staff)->post(route('admin.orders.mark-paid', $order))->assertRedirect();

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hoàn tất')
            ->assertSee('Hủy đơn');

        $this->post(route('admin.orders.complete', $order))->assertRedirect();
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('status');
    }

    /** @return array{0: ProductVariant, 1: ProductVariant, 2: InventoryItem} */
    private function seedMixed(): array
    {
        $sale = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'slug' => 'ao-close-'.uniqid(),
        ]);
        $saleVariant = ProductVariant::factory()->create([
            'product_id' => $sale->id,
            'sku' => 'CL-SALE-'.uniqid(),
            'sale_price' => 100000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $saleVariant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $rent = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'slug' => 'vot-close-'.uniqid(),
        ]);
        $rentVariant = ProductVariant::factory()->create([
            'product_id' => $rent->id,
            'sku' => 'CL-RENT-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
            'rental_price_per_week' => 480000,
            'deposit_amount' => 300000,
        ]);
        $item = InventoryItem::factory()->create([
            'product_variant_id' => $rentVariant->id,
            'asset_code' => 'CL-AST-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);

        return [$saleVariant, $rentVariant, $item];
    }

    /** @return array{0: ProductVariant, 1: InventoryItem} */
    private function seedRental(): array
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt closeout',
            'slug' => 'vot-closeout-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'CL-R-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
            'rental_price_per_week' => 480000,
            'deposit_amount' => 300000,
        ]);
        $item = InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'CL-R-AST-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);

        return [$variant, $item];
    }

    private function saleVariant(int $onHand): ProductVariant
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'slug' => 'ao-sale-close-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'CL-SO-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => 0,
        ]);

        return $variant;
    }

    private function addMixedCart(int $saleVariantId, int $rentVariantId): void
    {
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $saleVariantId,
            'quantity' => 2,
        ]);
        $this->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $rentVariantId,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ]);
    }

    private function payAll(User $customer, Order $order): void
    {
        $this->staffConfirmPendingPayments($order->id);
        $this->actingAs($customer);
    }

    private function checkoutAndPayRental(User $customer, User $staff, int $variantId): RentalBooking
    {
        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $variantId,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect();
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $booking = RentalBooking::query()->firstOrFail();
        $this->payAll($customer, $booking->order);
        $this->actingAs($staff)->post(route('admin.orders.confirm', $booking->order))->assertRedirect();

        return $booking->fresh(['order']);
    }

    /** @param  array<string, string>  $overrides */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'shipping_name' => 'Nguyen Van Close',
            'shipping_phone' => '0902222444',
            'shipping_address' => '1 Đường Test',
            'payment_method' => 'bank_transfer',
        ], $overrides);
    }
}
