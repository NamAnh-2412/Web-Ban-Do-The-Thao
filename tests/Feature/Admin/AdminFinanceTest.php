<?php

namespace Tests\Feature\Admin;

use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_open_finance(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.finance.index'))
            ->assertForbidden();
    }

    public function test_finance_counts_each_order_once_preferring_collected_payment(): void
    {
        $admin = User::factory()->admin()->create();

        $momo = $this->makeOrder([
            'shipping_name' => 'Khách Finance MoMo',
            'grand_total' => 777000,
            'status' => OrderStatus::Paid,
        ]);
        Payment::factory()->create([
            'order_id' => $momo->id,
            'user_id' => $momo->user_id,
            'method' => PaymentMethod::Momo,
            'amount' => 777000,
            'status' => PaymentStatus::Failed,
        ]);
        Payment::factory()->create([
            'order_id' => $momo->id,
            'user_id' => $momo->user_id,
            'method' => PaymentMethod::Momo,
            'amount' => 777000,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $cod = $this->makeOrder([
            'shipping_name' => 'Khách Finance COD',
            'shipping_phone' => '0911222333',
            'grand_total' => 333000,
            'status' => OrderStatus::Pending,
        ]);
        Payment::factory()->create([
            'order_id' => $cod->id,
            'user_id' => $cod->user_id,
            'method' => PaymentMethod::Cod,
            'amount' => 333000,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee('Tài chính')
            ->assertSee('777.000')
            ->assertSee('333.000');

        $this->actingAs($admin)
            ->get(route('admin.finance.index', ['payment_status' => 'paid']))
            ->assertOk()
            ->assertSee('777.000')
            ->assertDontSee('333.000');

        $this->actingAs($admin)
            ->get(route('admin.finance.transactions', ['gateway' => 'cod', 'search' => '0911222333']))
            ->assertOk()
            ->assertSee('Giao dịch thanh toán')
            ->assertSee('Khách Finance COD')
            ->assertDontSee('Khách Finance MoMo');
    }

    public function test_admin_can_mark_cod_as_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder([
            'shipping_name' => 'COD chờ thu',
            'status' => OrderStatus::Pending,
            'grand_total' => 210000,
            'merchandise_total' => 210000,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => PaymentMethod::Cod,
            'amount' => 210000,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.finance.transactions'))
            ->patch(route('admin.finance.update-status', $order), [
                'payment_status' => 'paid',
                'current_payment_status' => 'pending',
                'current_order_status' => OrderStatus::Pending->value,
                'current_payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.finance.transactions'))
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_admin_cannot_update_momo_payment_manually(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder([
            'status' => OrderStatus::Paid,
            'grand_total' => 150000,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => PaymentMethod::Momo,
            'amount' => 150000,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.finance.transactions'))
            ->patch(route('admin.finance.update-status', $order), [
                'payment_status' => 'refunded',
                'current_payment_status' => 'paid',
                'current_order_status' => OrderStatus::Paid->value,
                'current_payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.finance.transactions'))
            ->assertSessionHasErrors('payment_status');
    }

    public function test_stale_finance_update_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder([
            'status' => OrderStatus::Pending,
            'grand_total' => 120000,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => PaymentMethod::Cod,
            'amount' => 120000,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.finance.transactions'))
            ->patch(route('admin.finance.update-status', $order), [
                'payment_status' => 'paid',
                'current_payment_status' => 'failed',
                'current_order_status' => OrderStatus::Pending->value,
                'current_payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.finance.transactions'))
            ->assertSessionHasErrors('payment_status');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_cannot_collect_cod_for_cancelled_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder([
            'status' => OrderStatus::Cancelled,
            'grand_total' => 99000,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => PaymentMethod::Cod,
            'amount' => 99000,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.finance.transactions'))
            ->patch(route('admin.finance.update-status', $order), [
                'payment_status' => 'paid',
                'current_payment_status' => 'cancelled',
                'current_order_status' => OrderStatus::Cancelled->value,
                'current_payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.finance.transactions'))
            ->assertSessionHasErrors('payment_status');
    }

    public function test_finance_export_downloads_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeOrder([
            'shipping_name' => 'Khách CSV Finance',
            'grand_total' => 555000,
            'status' => OrderStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.export'));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Khách CSV Finance', $csv);
        $this->assertStringContainsString('555000', $csv);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeOrder(array $overrides = []): Order
    {
        $customer = User::factory()->create();

        return Order::factory()->create(array_merge([
            'user_id' => $customer->id,
            'channel' => OrderChannel::Online,
            'shipping_name' => $customer->name,
            'shipping_phone' => '0900000000',
            'shipping_address' => '12 Nguyễn Huệ',
            'grand_total' => 200000,
            'merchandise_total' => 200000,
            'status' => OrderStatus::Pending,
        ], $overrides));
    }
}
