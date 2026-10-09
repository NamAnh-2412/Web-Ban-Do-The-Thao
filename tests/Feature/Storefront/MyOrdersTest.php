<?php

namespace Tests\Feature\Storefront;

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_unified_page_has_purchase_and_rental_tabs(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('my-orders.index'))
            ->assertOk()
            ->assertSee('Đơn hàng của tôi', false)
            ->assertSee('my-orders-tab', false)
            ->assertSee(route('my-orders.index', ['tab' => 'mua']), false)
            ->assertSee(route('my-orders.index', ['tab' => 'thue']), false)
            ->assertSee('Đơn hàng #', false);

        $this->actingAs($user)
            ->get('/don-hang')
            ->assertRedirect('/don-cua-toi?tab=mua');
    }
}
