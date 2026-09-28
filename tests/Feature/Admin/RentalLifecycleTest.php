<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Enums\IncidentType;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalWriter;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_checkout_creates_booking_and_split_payments(): void
    {
        [$variant, $item] = $this->seedRentalItem();
        $customer = User::factory()->create();

        $this->actingAs($customer);
        $this->addRentalToCart($variant->id);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $booking = RentalBooking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($customer->id, $booking->user_id);
        $this->assertSame($item->id, $booking->inventory_item_id);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame('2026-09-01', $booking->start_date->toDateString());
        $this->assertSame('2026-09-03', $booking->end_date->toDateString());
        $this->assertEquals(240000, (float) $booking->rental_amount);
        $this->assertEquals(300000, (float) $booking->deposit_amount);

        $kinds = Payment::query()->where('order_id', $booking->order_id)->pluck('kind')->map->value->sort()->values()->all();
        $this->assertSame(['deposit', 'merchandise'], $kinds);
    }

    public function test_second_customer_cannot_rent_same_item_on_overlapping_dates(): void
    {
        [$variant] = $this->seedRentalItem();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first);
        $this->addRentalToCart($variant->id);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();
        $this->assertSame(1, RentalBooking::query()->count());

        $this->actingAs($second)
            ->from(route('cart.index'))
            ->post('/gio-hang', [
                'line_type' => 'rental',
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'rental_start' => '2026-09-01',
                'rental_end' => '2026-09-03',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1, RentalBooking::query()->count());
    }

    public function test_staff_activates_and_returns_rental_after_order_is_paid(): void
    {
        [$variant, $item] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();

        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(ItemStatus::Rented, $item->fresh()->status);

        $this->actingAs($staff)
            ->get(route('admin.rentals.index'))
            ->assertOk()
            ->assertSee('Lịch thuê')
            ->assertSee($variant->sku)
            ->assertSee('Đã xác nhận');

        $this->actingAs($staff)
            ->from(route('admin.rentals.show', $booking))
            ->post(route('admin.rentals.return', $booking), ['condition' => 'good'])
            ->assertSessionHasErrors('status');

        $this->actingAs($staff)
            ->post(route('admin.rentals.activate', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Active, $booking->fresh()->status);
        $this->assertSame('processing', $booking->order->fresh()->status->value);

        $this->travelTo('2026-09-03 10:00:00');
        $this->actingAs($staff)
            ->post(route('admin.rentals.return', $booking), ['condition' => 'good'])
            ->assertRedirect();
        $this->travelBack();

        $booking = $booking->fresh()->load('incidents', 'rentalReturn');
        $this->assertSame(BookingStatus::Returned, $booking->status);
        $this->assertNotNull($booking->rentalReturn);
        $this->assertCount(0, $booking->incidents);
        $this->assertSame(300000.0, app(RentalWriter::class)->depositOutcome($booking)['refund_suggested']);
        $this->assertSame(ItemStatus::Available, $item->fresh()->status);
        $this->assertSame('completed', $booking->order->fresh()->status->value);
    }

    public function test_late_return_creates_late_incident_and_reduces_deposit_refund(): void
    {
        [$variant] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking));

        $this->travelTo('2026-09-05 10:00:00');
        $this->actingAs($staff)
            ->post(route('admin.rentals.return', $booking), ['condition' => 'good'])
            ->assertRedirect();
        $this->travelBack();

        $booking = $booking->fresh()->load('incidents');
        $this->assertSame(BookingStatus::Returned, $booking->status);
        $this->assertSame(IncidentType::Late, $booking->incidents->first()->type);
        $this->assertEquals(160000, (float) $booking->incidents->first()->fee_amount);
        $this->assertSame(140000.0, app(RentalWriter::class)->depositOutcome($booking)['refund_suggested']);
    }

    public function test_damaged_return_deducts_compensation_from_deposit(): void
    {
        [$variant, $item] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking));
        $this->travelTo('2026-09-03 10:00:00');
        $this->actingAs($staff)->post(route('admin.rentals.return', $booking), [
            'condition' => 'damaged',
            'incident_description' => 'Rách lưới vợt',
            'incident_fee' => 100000,
        ])->assertRedirect();
        $this->travelBack();

        $booking = $booking->fresh()->load('incidents');
        $this->assertSame(IncidentType::Damage, $booking->incidents->first()->type);
        $this->assertEquals(100000, (float) $booking->incidents->first()->fee_amount);
        $outcome = app(RentalWriter::class)->depositOutcome($booking);
        $this->assertSame(200000.0, $outcome['refund_suggested']);
        $this->assertSame(0.0, $outcome['extra_due']);
        $this->assertSame(ItemStatus::Maintenance, $item->fresh()->status);

        $this->actingAs($customer)
            ->get(route('orders.show', $booking->order_id))
            ->assertOk()
            ->assertSee('Đền bù / trừ cọc')
            ->assertSee('Hư hỏng')
            ->assertSee('Rách lưới vợt')
            ->assertSee('200.000');

        $this->actingAs($staff)
            ->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Hoàn cọc 200.000đ');
    }

    public function test_lost_return_keeps_full_deposit_as_compensation(): void
    {
        [$variant] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);

        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking));
        $this->travelTo('2026-09-03 10:00:00');
        $this->actingAs($staff)->post(route('admin.rentals.return', $booking), [
            'condition' => 'lost',
        ])->assertRedirect();
        $this->travelBack();

        $booking = $booking->fresh()->load('incidents');
        $this->assertSame(IncidentType::Lost, $booking->incidents->first()->type);
        $this->assertEquals(300000, (float) $booking->incidents->first()->fee_amount);
        $outcome = app(RentalWriter::class)->depositOutcome($booking);
        $this->assertSame(0.0, $outcome['refund_suggested']);
        $this->assertSame(0.0, $outcome['extra_due']);

        $this->actingAs($staff)
            ->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Mất đồ')
            ->assertSee('không còn cọc để hoàn');
    }

    public function test_staff_can_approve_extension_when_item_is_still_free(): void
    {
        [$variant] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);
        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking));

        $extension = app(RentalWriter::class)->requestExtension($booking->fresh(), '2026-09-06');
        $this->assertEquals(240000, (float) $extension->extra_amount);

        $this->actingAs($staff)
            ->post(route('admin.rentals.extensions.approve', [$booking, $extension]))
            ->assertRedirect();

        $booking = $booking->fresh();
        $this->assertSame('2026-09-06', $booking->end_date->toDateString());
        $this->assertEquals(480000, (float) $booking->rental_amount);
    }

    public function test_pos_can_create_paid_rental_order(): void
    {
        [$variant] = $this->seedRentalItem();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();

        $this->actingAs($staff)->post(route('admin.pos.add'), [
            'line_type' => 'rental',
            'product_variant_id' => $variant->id,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect(route('admin.pos.index'));

        $this->actingAs($staff)->post(route('admin.pos.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $booking = RentalBooking::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Active, $booking->status);
        $this->assertSame('processing', $booking->order->status->value);
        $this->assertSame('pos', $booking->order->channel->value);
    }

    public function test_paid_rental_before_handover_can_be_cancelled(): void
    {
        [$variant, $item] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);
        $order = $booking->order;

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hủy đơn')
            ->assertDontSee('Hoàn tiền')
            ->assertSee('Xem lịch');

        $this->actingAs($staff)
            ->get(route('admin.rentals.show', $booking))
            ->assertOk()
            ->assertSee('Đơn #'.$order->id);

        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(ItemStatus::Available, $item->fresh()->status);
        $this->assertTrue(Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Refund)->exists());
    }

    public function test_paid_rental_order_cannot_be_cancelled_after_handover(): void
    {
        [$variant, $item] = $this->seedRentalItem();
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $booking = $this->checkoutAndPayRental($customer, $staff, $variant->id);
        $this->actingAs($staff)->post(route('admin.rentals.activate', $booking))->assertRedirect();

        $order = $booking->order->fresh();
        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Hoàn tiền')
            ->assertDontSee('Hủy đơn');

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.cancel', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame('processing', $order->fresh()->status->value);
        $this->assertSame(BookingStatus::Active, $booking->fresh()->status);
        $this->assertSame(ItemStatus::Rented, $item->fresh()->status);
        $this->assertFalse(Payment::query()->where('order_id', $order->id)->where('kind', PaymentKind::Refund)->exists());
    }

    public function test_customer_can_rent_quantity_two_and_get_two_bookings(): void
    {
        [$variant] = $this->seedRentalItem();
        InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'RENT-AST-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect();

        $this->get('/gio-hang')->assertOk()->assertSee('Vợt thuê test');

        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $bookings = RentalBooking::query()->where('user_id', $customer->id)->get();
        $this->assertCount(2, $bookings);
        $this->assertCount(2, $bookings->pluck('inventory_item_id')->unique());
        $this->assertEquals(480000, (float) $bookings->first()->order->rental_total);
        $this->assertEquals(600000, (float) $bookings->first()->order->deposit_total);
    }

    public function test_same_day_bookings_are_one_session_to_handover_and_return(): void
    {
        [$variant] = $this->seedRentalItem();
        InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'RENT-AST-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);
        $customer = User::factory()->create(['name' => 'Khach Buoi']);
        $staff = User::factory()->staff()->create();

        $this->actingAs($customer)->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect();
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $bookings = RentalBooking::query()->where('user_id', $customer->id)->orderBy('id')->get();
        $this->assertCount(2, $bookings);
        $order = $bookings->first()->order;
        $this->staffConfirmPendingPayments($order->id);
        $this->actingAs($staff)->post(route('admin.orders.confirm', $order))->assertRedirect();

        $this->assertTrue($bookings->fresh()->every(fn ($row) => $row->status === BookingStatus::Confirmed));

        $this->get(route('admin.rentals.index'))
            ->assertOk()
            ->assertSee('2 món')
            ->assertSee('Khach Buoi')
            ->assertSee('Đã xác nhận');

        $lead = $bookings->first();
        $this->get(route('admin.rentals.show', $lead))
            ->assertOk()
            ->assertSee('Buổi thuê')
            ->assertSee('cả buổi');

        $this->post(route('admin.rentals.activate', $lead))->assertRedirect();
        $this->assertTrue($bookings->fresh()->every(fn ($row) => $row->status === BookingStatus::Active));

        $this->travelTo('2026-09-03 10:00:00');
        $this->post(route('admin.rentals.return', $lead), [
            'items' => [
                $bookings[0]->id => ['condition' => 'good'],
                $bookings[1]->id => ['condition' => 'good'],
            ],
        ])->assertRedirect();
        $this->travelBack();

        $this->assertTrue($bookings->fresh()->every(fn ($row) => $row->status === BookingStatus::Returned));
        $this->assertSame('completed', $order->fresh()->status->value);
    }

    public function test_rental_quantity_cannot_exceed_free_items(): void
    {
        [$variant] = $this->seedRentalItem();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->from(route('cart.index'))
            ->post('/gio-hang', [
                'line_type' => 'rental',
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'rental_start' => '2026-09-01',
                'rental_end' => '2026-09-03',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, RentalBooking::query()->count());
    }

    /** @return array{0: ProductVariant, 1: InventoryItem} */
    private function seedRentalItem(): array
    {
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt thuê test',
            'slug' => 'vot-thue-test-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'RENT-TEST-'.uniqid(),
            'sale_price' => null,
            'rental_price_per_day' => 80000,
            'rental_price_per_week' => 480000,
            'deposit_amount' => 300000,
        ]);
        $item = InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'RENT-AST-'.uniqid(),
            'status' => ItemStatus::Available,
        ]);

        return [$variant, $item];
    }

    private function addRentalToCart(int $variantId): void
    {
        $this->post('/gio-hang', [
            'line_type' => 'rental',
            'product_variant_id' => $variantId,
            'rental_start' => '2026-09-01',
            'rental_end' => '2026-09-03',
        ])->assertRedirect();
    }

    private function checkoutAndPayRental(User $customer, User $staff, int $variantId): RentalBooking
    {
        $this->actingAs($customer);
        $this->addRentalToCart($variantId);
        $this->post('/thanh-toan', $this->checkoutPayload())->assertRedirect();

        $booking = RentalBooking::query()->firstOrFail();
        $this->staffConfirmPendingPayments($booking->order_id, $staff);

        $this->actingAs($staff)
            ->post(route('admin.orders.confirm', $booking->order))
            ->assertRedirect();

        return $booking->fresh(['order']);
    }

    /** @param  array<string, string>  $overrides */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'shipping_name' => 'Nguyen Van Thue',
            'shipping_phone' => '0902222333',
            'shipping_address' => 'Nhận tại nhà',
            'payment_method' => 'bank_transfer',
        ], $overrides);
    }
}
