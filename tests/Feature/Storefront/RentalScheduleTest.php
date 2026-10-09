<?php

namespace Tests\Feature\Storefront;

use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_open_rentals_and_can_request_an_extension(): void
    {
        $user = User::factory()->create();
        $booking = RentalBooking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Active,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);
        $booking->load('variant.product');

        $this->actingAs($user)
            ->get(route('my-orders.index', ['tab' => 'thue']))
            ->assertOk()
            ->assertSee('Đơn của tôi', false)
            ->assertSee($booking->variant->product->name)
            ->assertSee('Gửi yêu cầu gia hạn');

        $this->actingAs($user)
            ->from(route('my-orders.index', ['tab' => 'thue']))
            ->post(route('orders.extensions.store', $booking->order_id), [
                'booking_id' => $booking->id,
                'new_end_date' => '2026-10-08',
            ])
            ->assertRedirect(route('my-orders.index', ['tab' => 'thue']));

        $this->assertDatabaseHas('rental_extensions', [
            'rental_booking_id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    public function test_legacy_rental_schedule_url_redirects_to_my_orders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/lich-thue')
            ->assertRedirect('/don-cua-toi?tab=thue');
    }

    public function test_store_account_cannot_open_the_customer_schedule(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('my-orders.index', ['tab' => 'thue']))
            ->assertRedirect(route('admin.dashboard'));
    }
}
