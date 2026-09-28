<?php

namespace Tests\Feature\Storefront;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Models\InventoryStockReservation;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_cart_checkout_locks_stock_and_creates_two_payments(): void
    {
        [$saleVariant, $rentVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 10);
        $this->stockRental($rentVariant->id);

        $user = User::factory()->create(['password' => 'password123']);
        $this->actingAs($user);

        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $rentVariant->id,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect();

        $this->post('/thanh-toan', [
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0901111222',
            'shipping_address' => '1 Đường Test, Q.1',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status->value);
        $this->assertCount(2, $order->items);
        $this->assertGreaterThan(0, (float) $order->merchandise_total);
        $this->assertGreaterThan(0, (float) $order->rental_total);
        $this->assertGreaterThan(0, (float) $order->deposit_total);

        $payments = Payment::query()->where('order_id', $order->id)->get();
        $this->assertSame(['deposit', 'merchandise'], $payments->pluck('kind')->map->value->sort()->values()->all());
        $this->assertTrue($payments->every(fn (Payment $row) => $row->status->value === 'pending'));
        $this->assertTrue($payments->every(fn (Payment $row) => $row->method->value === 'bank_transfer'));
        $this->assertTrue($payments->every(fn (Payment $row) => $row->paid_at === null));

        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(8, $stock->quantity_on_hand - $stock->quantity_reserved);
        $this->assertTrue(RentalBooking::query()->where('order_id', $order->id)->exists());
    }

    public function test_staff_confirming_bank_payments_marks_order_paid_and_commits_stock(): void
    {
        [$saleVariant, $rentVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 10);
        $this->stockRental($rentVariant->id);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->addMixedCart($saleVariant->id, $rentVariant->id);
        $this->get('/thanh-toan')
            ->assertOk()
            ->assertSee('Chuyển khoản QR')
            ->assertDontSee('Tiền mặt');
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->staffConfirmPendingPayments($order->id);

        $paid = Payment::query()->where('order_id', $order->id)->get();
        $this->assertTrue($paid->every(fn (Payment $row) => $row->status->value === 'completed'));
        $this->assertTrue($paid->every(fn (Payment $row) => $row->method->value === 'bank_transfer'));
        $this->assertTrue($paid->every(fn (Payment $row) => $row->paid_at !== null));
        $this->assertTrue($paid->every(fn (Payment $row) => str_starts_with((string) $row->provider_txn_id, 'BANK-')));

        $this->assertSame('pending', $order->fresh()->status->value);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status->value);

        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(8, $stock->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);

        $this->actingAs($user)
            ->get(route('orders.show', $order->id))
            ->assertOk()
            ->assertSee('Đã thanh toán')
            ->assertSee('Tiền mua + thuê')
            ->assertDontSee('Thanh toán tiền mặt');
    }

    public function test_bank_transfer_needs_staff_confirm(): void
    {
        [$saleVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 5);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())
            ->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertSame('bank_transfer', $payment->method->value);

        $this->get(route('orders.show', $order->id))
            ->assertOk()
            ->assertSee('Chờ nhân viên xác nhận đã nhận tiền')
            ->assertSee('Vietcombank')
            ->assertSee('0123456789')
            ->assertSee('WTT'.$order->id)
            ->assertDontSee('Thanh toán tiền mặt');

        $this->assertSame('pending', $payment->fresh()->status->value);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.payments.confirm', $payment))
            ->assertRedirect();

        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame('pending', $order->fresh()->status->value);

        $this->actingAs($admin)
            ->post(route('admin.orders.confirm', $order))
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_customer_cannot_see_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('orders.show', $order->id))
            ->assertNotFound();
    }

    public function test_cannot_add_sale_line_when_stock_is_insufficient(): void
    {
        [$saleVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 1);

        $this->actingAs(User::factory()->create())
            ->from(route('catalog.index'))
            ->post('/gio-hang', [
                'line_type' => 'sale',
                'product_variant_id' => $saleVariant->id,
                'quantity' => 5,
            ])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHasErrors('quantity');
    }

    public function test_admin_can_mark_paid_and_cancel_releases_stock(): void
    {
        [$saleVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 5);

        $customer = User::factory()->create();
        $this->actingAs($customer);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee((string) $order->id);

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status->value);
        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(5, $stock->quantity_on_hand - $stock->quantity_reserved);
    }

    public function test_admin_can_cancel_paid_online_sale_and_restock(): void
    {
        [$saleVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 10);

        $customer = User::factory()->create();
        $this->actingAs($customer);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 2,
        ]);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $admin = User::factory()->admin()->create();
        $this->staffConfirmPendingPayments($order->id, $admin);

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-paid', $order))
            ->assertRedirect();

        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(8, $stock->quantity_on_hand);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hủy đơn')
            ->assertDontSee('Hoàn tiền');

        $this->from(route('admin.payments.index'))
            ->post(route('admin.payments.refund'), [
                'order_id' => $order->id,
                'amount' => 10000,
                'note' => 'Hoàn lệch',
            ])
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHasErrors('order_id');

        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $order = $order->fresh();
        $this->assertSame('cancelled', $order->status->value);
        $stock = $stock->fresh();
        $this->assertSame(10, $stock->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);

        $reservation = InventoryStockReservation::query()->where('order_id', $order->id)->first();
        $this->assertSame(ReservationStatus::Released, $reservation->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'kind' => PaymentKind::Refund->value,
            'amount' => 438000,
        ]);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Đơn mua[\s\S]{0,250}?>0</', $html);

        $inv = $this->get(route('admin.inventory.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/'.preg_quote($saleVariant->sku, '/').'[\s\S]{0,800}?>2</', $inv);
    }

    public function test_paid_mixed_order_cannot_be_cancelled_after_handover(): void
    {
        [$saleVariant, $rentVariant] = $this->seedCatalog();
        $this->stockSale($saleVariant->id, 10);
        $this->stockRental($rentVariant->id);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->addMixedCart($saleVariant->id, $rentVariant->id);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->staffConfirmPendingPayments($order->id);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->post(route('admin.orders.mark-paid', $order))
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status->value);

        $booking = RentalBooking::query()->where('order_id', $order->id)->first();
        $this->post(route('admin.rentals.activate', $booking))->assertRedirect();
        $this->assertSame('processing', $order->fresh()->status->value);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hoàn tiền')
            ->assertDontSee('Hủy đơn');

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame('processing', $order->fresh()->status->value);
        $stock = InventoryStock::query()->where('product_variant_id', $saleVariant->id)->first();
        $this->assertSame(8, $stock->quantity_on_hand);
        $this->assertFalse(Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Refund)->exists());
    }

    /** @return array{0: ProductVariant, 1: ProductVariant} */
    private function seedCatalog(): array
    {
        $sale = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo checkout',
            'slug' => 'ao-checkout-'.uniqid(),
        ]);
        $saleVariant = ProductVariant::factory()->create([
            'product_id' => $sale->id,
            'sku' => 'CHK-SALE-'.uniqid(),
            'sale_price' => 219000,
            'rental_price_per_day' => null,
            'deposit_amount' => null,
        ]);

        $rent = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt checkout',
            'slug' => 'vot-checkout-'.uniqid(),
        ]);
        $rentVariant = ProductVariant::factory()->create([
            'product_id' => $rent->id,
            'sku' => 'CHK-RENT-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
            'rental_price_per_week' => 480000,
            'deposit_amount' => 300000,
        ]);

        return [$saleVariant, $rentVariant];
    }

    private function stockSale(int $variantId, int $onHand): void
    {
        InventoryStock::factory()->create([
            'product_variant_id' => $variantId,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => 0,
        ]);
    }

    private function stockRental(int $variantId): void
    {
        InventoryItem::factory()->create([
            'product_variant_id' => $variantId,
            'asset_code' => 'CHK-RENT-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);
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

    /** @param  array<string, string>  $overrides */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0901111222',
            'shipping_address' => '1 Đường Test, Q.1',
            'payment_method' => 'bank_transfer',
        ], $overrides);
    }
}
