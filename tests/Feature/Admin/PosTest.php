<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Models\InventoryStockReservation;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_paid_pos_order_for_customer(): void
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo POS',
            'slug' => 'ao-pos-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POS-SALE-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create(['name' => 'Khach quay']);

        $this->actingAs($staff)
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Bán tại quầy')
            ->assertSee('Áo POS')
            ->assertSee('Tiền mặt')
            ->assertSee('Chuyển khoản')
            ->assertSee('Khách trả')
            ->assertSee('XÁC NHẬN');

        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertRedirect(route('admin.pos.index'));

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'action' => 'pay',
            'payment_method' => 'cash',
        ])->assertRedirect(route('admin.pos.index'));

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderChannel::Pos, $order->channel);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('Nhận tại quầy', $order->shipping_address);
        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertSame('cash', $payment->method->value);
        $this->assertSame('completed', $payment->status->value);
        $this->assertNotNull($payment->paid_at);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Thanh toán')
            ->assertDontSee('Xác nhận thanh toán')
            ->assertDontSee('Chốt đơn');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(4, $stock->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_staff_can_cancel_paid_pos_sale_and_restock(): void
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo hủy POS',
            'slug' => 'ao-huy-pos-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POS-CANCEL-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect(route('admin.pos.index'));

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->assertSame(OrderStatus::Paid, $order->status);

        $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(8, $stock->quantity_on_hand);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Đơn mua[\s\S]{0,250}?>1</', $html);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hủy đơn')
            ->assertDontSee('Hoàn tiền');

        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $order = $order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $stock = $stock->fresh();
        $this->assertSame(10, $stock->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);

        $reservation = InventoryStockReservation::query()->where('order_id', $order->id)->first();
        $this->assertSame(ReservationStatus::Released, $reservation->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'kind' => PaymentKind::Refund->value,
            'amount' => 300000,
        ]);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Đơn mua[\s\S]{0,250}?>0</', $html);

        $inv = $this->get(route('admin.inventory.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/'.preg_quote($variant->sku, '/').'[\s\S]{0,800}?>2</', $inv);
    }

    public function test_staff_can_create_walkin_customer_on_pos(): void
    {
        $product = Product::factory()->create(['offer_mode' => OfferMode::Sale]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sale_price' => 99000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'walkin',
            'walkin_name' => 'Nguyen Van Quay',
            'walkin_phone' => '0909999888',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'Nguyen Van Quay',
            'phone' => '0909999888',
            'role' => 'customer',
        ]);
        $order = Order::query()->where('channel', 'pos')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Paid, $order->status);
    }

    public function test_staff_can_save_pos_order_without_settling(): void
    {
        $product = Product::factory()->create(['offer_mode' => OfferMode::Sale]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sale_price' => 50000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'walkin',
            'walkin_name' => 'Khách lẻ',
            'action' => 'save',
        ])->assertRedirect(route('admin.pos.index'));

        $order = Order::query()->where('channel', 'pos')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame('Khách lẻ', $order->user->name);

        $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(2, $stock->quantity_on_hand);
        $this->assertSame(1, $stock->quantity_reserved);
    }

    public function test_staff_can_remove_extra_pos_ticket(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.pos.index'))->assertOk();
        $this->post(route('admin.pos.tickets.store'))->assertRedirect();

        $desk = session('admin_pos_desk');
        $this->assertCount(2, $desk['tickets']);
        $extraId = $desk['active'];

        $this->delete(route('admin.pos.tickets.destroy', $extraId))->assertRedirect();
        $this->assertCount(1, session('admin_pos_desk')['tickets']);
        $this->assertArrayNotHasKey($extraId, session('admin_pos_desk')['tickets']);
    }

    public function test_staff_cannot_remove_last_pos_ticket(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.pos.index'))->assertOk();

        $onlyId = session('admin_pos_desk')['active'];
        $this->delete(route('admin.pos.tickets.destroy', $onlyId))->assertRedirect();

        $this->assertCount(1, session('admin_pos_desk')['tickets']);
        $this->assertArrayHasKey($onlyId, session('admin_pos_desk')['tickets']);
    }

    public function test_customer_cannot_open_pos(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.pos.index'))->assertForbidden();
    }

    public function test_staff_can_add_rental_quantity_and_checkout_creates_bookings(): void
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt POS thuê nhiều',
            'slug' => 'vot-pos-thue-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POS-RENT-QTY-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
            'rental_price_per_week' => 480000,
            'deposit_amount' => 200000,
        ]);
        InventoryItem::factory()->count(2)->create([
            'product_variant_id' => $variant->id,
            'status' => ItemStatus::Available,
        ]);

        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Số lượng');

        $this->from(route('admin.pos.index'))
            ->post(route('admin.pos.add'), [
                'line_type' => 'rental',
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'rental_start' => '2026-09-01',
                'rental_end' => '2026-09-03',
            ])
            ->assertRedirect(route('admin.pos.index'));

        $this->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Vợt POS thuê nhiều');

        $desk = session('admin_pos_desk');
        $ticket = $desk['tickets'][$desk['active']];
        $this->assertCount(1, $ticket['lines']);
        $this->assertSame(2, (int) $ticket['lines'][0]['quantity']);

        $this->post(route('admin.pos.add'), [
            'line_type' => 'rental',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect(route('admin.pos.index'))->assertSessionHasErrors('quantity');

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $bookings = RentalBooking::query()->where('user_id', $customer->id)->get();
        $this->assertCount(2, $bookings);
        $this->assertCount(2, $bookings->pluck('inventory_item_id')->unique());

        $order = $bookings->first()->order;
        $this->assertTrue($bookings->fresh()->every(fn ($row) => $row->status === BookingStatus::Active));
        $this->assertSame('processing', $order->fresh()->status->value);

        $this->get(route('admin.rentals.index'))
            ->assertOk()
            ->assertSee('2 món')
            ->assertSee('Đang thuê');

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('status');
    }

    public function test_pos_can_save_order_as_bank_transfer(): void
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo CK POS',
            'slug' => 'ao-ck-pos-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POS-CK-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertRedirect(route('admin.pos.index'));

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'payment_method' => 'bank_transfer',
            'action' => 'save',
        ])->assertRedirect(route('admin.pos.index'));

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertSame('bank_transfer', $payment->method->value);
        $this->assertSame('pending', $payment->status->value);
    }

    public function test_pos_bank_transfer_pay_settles_immediately(): void
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo CK xác nhận POS',
            'slug' => 'ao-ck-pay-pos-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POS-CK-PAY-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertRedirect(route('admin.pos.index'));

        $this->actingAs($staff)
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Vietcombank')
            ->assertSee('0123456789')
            ->assertSee('WTT');

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'payment_method' => 'bank_transfer',
            'action' => 'pay',
        ])->assertRedirect(route('admin.pos.index'));

        $order = Order::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertSame('bank_transfer', $payment->method->value);
        $this->assertSame('completed', $payment->status->value);
        $this->assertStringContainsString('Đã nhận chuyển khoản tại quầy.', (string) $payment->note);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Xác nhận thanh toán')
            ->assertDontSee('Xác nhận nhận tiền');
    }
}
