<?php

namespace Tests\Feature\Notification;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Mail\OutboundMail;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Services\NotificationWriter;
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
            'dedupe_key' => 'order_confirmed:'.$order->id,
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

    public function test_artisan_rental_due_reminder_triggers_24h_ahead_and_is_idempotent(): void
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

        $dueKey = 'rental_due:'.$user->id.':'.$due->order_id.':2026-09-05';
        $dueRow = Notification::query()
            ->where('type', NotificationType::RentalDueReminder)
            ->where('dedupe_key', $dueKey)
            ->first();

        $this->assertNotNull($dueRow);
        $this->assertSame('emails.rental-due-reminder', $dueRow->payload['template'] ?? null);
        $this->assertContains($due->id, $dueRow->payload['booking_ids'] ?? []);

        Mail::assertSent(OutboundMail::class, function (OutboundMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->viewName === 'emails.rental-due-reminder'
                && str_contains($mail->subjectLine, 'Nhắc hạn trả');
        });

        $this->assertSame(BookingStatus::Overdue, $late->fresh()->status);

        $this->artisan('webthethao:notify-rentals', ['--date' => '2026-09-04'])
            ->assertSuccessful();

        $this->assertSame(2, Notification::query()->where('user_id', $user->id)->count());

        $this->travelBack();
    }

    public function test_due_reminder_skips_bookings_not_matching_lead_window(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-04 08:00:00');

        $user = User::factory()->create();
        RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-06',
        ]);

        $this->artisan('webthethao:notify-rentals', ['--date' => '2026-09-04'])
            ->assertSuccessful();

        $this->assertSame(
            0,
            Notification::query()->where('type', NotificationType::RentalDueReminder)->count()
        );

        $this->travelBack();
    }

    public function test_due_reminder_groups_same_order_end_date_into_one_email(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-04 08:00:00');

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $first = RentalBooking::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);
        $second = RentalBooking::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => BookingStatus::Confirmed,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);

        $this->artisan('webthethao:notify-rentals', ['--date' => '2026-09-04'])
            ->assertSuccessful();

        $row = Notification::query()
            ->where('type', NotificationType::RentalDueReminder)
            ->where('dedupe_key', 'rental_due:'.$user->id.':'.$order->id.':2026-09-05')
            ->first();

        $this->assertNotNull($row);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $row->payload['booking_ids'] ?? []
        );
        $this->assertSame(1, Notification::query()->where('type', NotificationType::RentalDueReminder)->count());
        Mail::assertSent(OutboundMail::class, 1);

        $this->travelBack();
    }

    public function test_failed_notification_is_retried_on_next_enqueue(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $row = Notification::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'type' => NotificationType::RentalDueReminder,
            'status' => NotificationStatus::Failed,
            'error_message' => 'SMTP down',
            'dedupe_key' => 'rental_due:retry-test',
            'payload' => [
                'dedupe_key' => 'rental_due:retry-test',
                'template' => 'emails.rental-due-reminder',
                'template_data' => [
                    'customerName' => $user->name,
                    'orderId' => 1,
                    'endDate' => '2026-09-05',
                    'daysUntilDue' => 1,
                    'items' => [],
                    'scheduleUrl' => url('/don-cua-toi?tab=thue'),
                ],
            ],
        ]);

        $writer = app(NotificationWriter::class);
        [$again, $created] = $writer->enqueueOnce(
            $user->id,
            $user->email,
            NotificationType::RentalDueReminder,
            'Nhắc lại',
            'body',
            ['dedupe_key' => 'rental_due:retry-test'],
        );

        $this->assertFalse($created);
        $this->assertSame($row->id, $again->id);
        $this->assertSame(NotificationStatus::Sent, $again->fresh()->status);
        $this->assertSame(1, Notification::query()->count());
        Mail::assertSent(OutboundMail::class, 1);
    }

    public function test_guest_cannot_see_notifications(): void
    {
        $this->get('/thong-bao')->assertRedirect(route('login'));
    }
}
