<?php

namespace Tests\Feature\Storefront;

use App\Domain\Chat\Enums\ChatAttachmentType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\Message;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
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
            ->assertSee('Tin nhắn')
            ->assertSee('Sản phẩm')
            ->assertSee('Đơn hàng');
    }

    public function test_customer_can_attach_product_card(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Giày chạy Trail Pro',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'body' => 'Còn size nào vậy?',
                'attachment_type' => ChatAttachmentType::Product->value,
                'attachment_id' => $product->id,
            ])
            ->assertRedirect(route('messages.show'));

        $message = Message::query()->firstOrFail();
        $this->assertSame(ChatAttachmentType::Product, $message->attachment_type);
        $this->assertSame($product->id, $message->attachment_id);
        $this->assertSame('Giày chạy Trail Pro', $message->attachment_payload['title'] ?? null);

        $this->actingAs($customer)
            ->get(route('messages.show'))
            ->assertOk()
            ->assertSee('Giày chạy Trail Pro')
            ->assertSee('Còn size nào vậy?');
    }

    public function test_customer_can_attach_own_order_card_without_body(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'grand_total' => 250000,
        ]);

        $this->actingAs($customer)
            ->post(route('messages.store'), [
                'body' => '',
                'attachment_type' => ChatAttachmentType::Order->value,
                'attachment_id' => $order->id,
            ])
            ->assertRedirect(route('messages.show'));

        $message = Message::query()->firstOrFail();
        $this->assertSame('', $message->body);
        $this->assertSame(ChatAttachmentType::Order, $message->attachment_type);
        $this->assertSame($order->id, $message->attachment_id);

        $conversation = Conversation::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertStringContainsString('[Đơn #'.$order->id.']', (string) $conversation->last_message);

        $this->actingAs($customer)
            ->get(route('messages.show'))
            ->assertOk()
            ->assertSee('Đơn #'.$order->id);
    }

    public function test_customer_cannot_attach_another_users_order(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($customer)
            ->from(route('messages.show'))
            ->post(route('messages.store'), [
                'body' => 'Xem giúp đơn này',
                'attachment_type' => ChatAttachmentType::Order->value,
                'attachment_id' => $order->id,
            ])
            ->assertRedirect(route('messages.show'))
            ->assertSessionHasErrors('attachment_id');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_customer_cannot_attach_inactive_product(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Hết hàng ẩn',
            'is_active' => false,
        ]);

        $this->actingAs($customer)
            ->from(route('messages.show'))
            ->post(route('messages.store'), [
                'attachment_type' => ChatAttachmentType::Product->value,
                'attachment_id' => $product->id,
            ])
            ->assertRedirect(route('messages.show'))
            ->assertSessionHasErrors('attachment_id');

        $this->assertSame(0, Message::query()->count());
    }

    public function test_staff_can_attach_customer_order_in_thread(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $conversation = Conversation::query()->create([
            'user_id' => $customer->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.messages.store', $conversation), [
                'body' => 'Đây là đơn bạn hỏi',
                'attachment_type' => ChatAttachmentType::Order->value,
                'attachment_id' => $order->id,
            ])
            ->assertRedirect(route('admin.messages.show', $conversation));

        $this->actingAs($staff)
            ->get(route('admin.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Đơn #'.$order->id)
            ->assertSee('Đây là đơn bạn hỏi');
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
