<?php

namespace Tests\Feature\Storefront;

use App\Domain\Chat\Models\Conversation;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_messages(): void
    {
        $this->get(route('messages.show'))->assertRedirect(route('login'));
    }

    public function test_customer_can_message_store_and_staff_can_reply(): void
    {
        $customer = User::factory()->create(['name' => 'Khách chat']);
        $staff = User::factory()->staff()->create();

        $this->actingAs($customer)
            ->post(route('messages.store'), ['body' => 'Đơn của mình giao khi nào?'])
            ->assertRedirect(route('messages.show'));

        $conversation = Conversation::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $customer->id,
            'body' => 'Đơn của mình giao khi nào?',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Khách chat')
            ->assertSee('Đơn của mình giao khi nào?');

        $this->actingAs($staff)
            ->post(route('admin.messages.store', $conversation), ['body' => 'Shop sẽ giao trong 2 ngày.'])
            ->assertRedirect(route('admin.messages.show', $conversation));

        $this->actingAs($customer)
            ->get(route('messages.show'))
            ->assertOk()
            ->assertSee('Shop sẽ giao trong 2 ngày.')
            ->assertSee('Cửa hàng')
            ->assertSee('Tin nhắn');
    }

    public function test_customers_do_not_share_conversations(): void
    {
        $one = User::factory()->create(['name' => 'Khách A']);
        $two = User::factory()->create(['name' => 'Khách B']);

        $this->actingAs($one)->post(route('messages.store'), ['body' => 'Tin mật của A']);

        $this->actingAs($two)
            ->get(route('messages.show'))
            ->assertOk()
            ->assertDontSee('Tin mật của A');
    }

    public function test_customer_cannot_open_admin_inbox(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.messages.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_start_chat_with_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.messages.start', $other))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');
    }

    public function test_admin_can_start_chat_with_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.messages.start', $customer))
            ->assertRedirect();

        $conversation = Conversation::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertSame($customer->id, $conversation->user_id);
    }
}
