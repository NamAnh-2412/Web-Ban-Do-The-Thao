<?php

namespace Tests\Feature\Notification;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Mail\OutboundMail;
use App\Domain\Notification\Models\Notification;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use App\Gateway\Services\NotificationOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_sends_order_confirmed_email(): void
    {
        Mail::fake();

        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'slug' => 'ao-notify-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'NTF-SALE-'.uniqid(),
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $user = User::factory()->create(['name' => 'Khach Notify']);
        $this->actingAs($user);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $this->post('/thanh-toan', [
            'shipping_name' => $user->name,
            'shipping_phone' => '0901111222',
            'shipping_address' => '1 Đường Test',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::OrderConfirmed->value,
            'status' => 'sent',
            'email' => $user->email,
        ]);

        Mail::assertSent(OutboundMail::class, function (OutboundMail $mail) use ($user, $order) {
            return $mail->hasTo($user->email)
                && str_contains($mail->subjectLine, (string) $order->id);
        });

        $this->get('/thong-bao')
            ->assertOk()
            ->assertSee('Đã nhận đơn #'.$order->id);
    }

    public function test_order_confirmed_is_idempotent(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $orchestrator = app(NotificationOrchestrator::class);

        $orchestrator->notifyOrderConfirmed($user, $order);
        $orchestrator->notifyOrderConfirmed($user, $order);

        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->count());
        Mail::assertSent(OutboundMail::class, 1);
    }

    public function test_artisan_rental_notify_is_idempotent(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-04 08:00:00');

        $user = User::factory()->create(['name' => 'Nguoi Thue']);
        $due = RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);
        RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
        ]);
        RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Pending,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);
        $late = RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-08-20',
            'end_date' => '2026-09-01',
        ]);

        $this->artisan('webthethao:notify-rentals', ['--date' => '2026-09-04'])
            ->assertSuccessful();

        $this->assertTrue(
            Notification::query()
                ->where('type', NotificationType::RentalDueReminder)
                ->where('payload->dedupe_key', 'rental_due:'.$due->id.':2026-09-05')
                ->exists()
        );

        $this->assertSame(BookingStatus::Overdue, $late->fresh()->status);

        $this->artisan('webthethao:notify-rentals', ['--date' => '2026-09-04'])
            ->assertSuccessful();

        $this->assertSame(2, Notification::query()->where('user_id', $user->id)->count());

        $this->travelBack();
    }

    public function test_guest_cannot_see_notifications(): void
    {
        $this->get('/thong-bao')->assertRedirect(route('login'));
    }
}
