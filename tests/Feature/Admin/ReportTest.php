<?php

namespace Tests\Feature\Admin;

use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Report\Services\ReportService;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_excludes_deposit_and_pending_and_cancelled_orders(): void
    {
        $customer = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'channel' => OrderChannel::Online,
            'merchandise_total' => 100000,
            'rental_total' => 80000,
            'deposit_total' => 300000,
            'discount_total' => 10000,
            'grand_total' => 470000,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'channel' => OrderChannel::Pos,
            'merchandise_total' => 50000,
            'rental_total' => 0,
            'deposit_total' => 0,
            'discount_total' => 0,
            'grand_total' => 50000,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'merchandise_total' => 999000,
            'grand_total' => 999000,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'merchandise_total' => 80000,
            'deposit_total' => 200000,
            'grand_total' => 280000,
        ]);

        $summary = app(ReportService::class)->summarize(now()->toDateString(), now()->toDateString());

        $this->assertEquals(220000.0, $summary['goods']);
        $this->assertEquals(220000.0, $summary['revenue']);
        $this->assertEquals(300000.0, $summary['deposit']);
        $this->assertEquals(300000.0, $summary['deposit_outstanding']);
        $this->assertEquals(520000.0, $summary['collected']);
        $this->assertEquals(170000.0, $summary['online_revenue']);
        $this->assertEquals(50000.0, $summary['pos_revenue']);
        $this->assertSame(4, $summary['order_total']);
        $this->assertSame(2, $summary['order_paid']);
        $this->assertSame(1, $summary['order_cancelled']);
    }

    public function test_deposit_refund_does_not_reduce_revenue(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Completed,
            'rental_total' => 80000,
            'deposit_total' => 300000,
            'grand_total' => 380000,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'kind' => PaymentKind::Refund,
            'amount' => 300000,
            'status' => PaymentStatus::Completed,
            'note' => 'Hoàn cọc lịch thuê #12',
        ]);

        $summary = app(ReportService::class)->summarize(now()->toDateString(), now()->toDateString());

        $this->assertEquals(80000.0, $summary['revenue']);
        $this->assertEquals(300000.0, $summary['deposit_refunded']);
        $this->assertEquals(0.0, $summary['deposit_outstanding']);
        $this->assertEquals(0.0, $summary['other_refunds']);
    }

    public function test_manual_refund_reduces_revenue_but_not_deposit(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'merchandise_total' => 100000,
            'grand_total' => 100000,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'kind' => PaymentKind::Refund,
            'amount' => 20000,
            'status' => PaymentStatus::Completed,
            'note' => 'Hoàn một phần đơn #'.$order->id,
        ]);

        $summary = app(ReportService::class)->summarize(now()->toDateString(), now()->toDateString());

        $this->assertEquals(80000.0, $summary['revenue']);
        $this->assertEquals(20000.0, $summary['other_refunds']);
        $this->assertEquals(0.0, $summary['deposit_refunded']);
    }

    public function test_admin_report_page_shows_separated_deposit(): void
    {
        Order::factory()->create([
            'status' => OrderStatus::Paid,
            'merchandise_total' => 100000,
            'rental_total' => 50000,
            'deposit_total' => 300000,
            'discount_total' => 0,
            'grand_total' => 450000,
        ]);

        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('không cộng vào doanh thu')
            ->assertSee('150.000đ')
            ->assertSee('300.000đ')
            ->getContent();

        $this->assertStringContainsString('Doanh thu', $html);
        $this->assertStringContainsString('Cọc', $html);
    }

    public function test_dashboard_week_chart_excludes_deposit(): void
    {
        Order::factory()->create([
            'status' => OrderStatus::Paid,
            'merchandise_total' => 100000,
            'rental_total' => 0,
            'deposit_total' => 300000,
            'discount_total' => 0,
            'grand_total' => 400000,
        ]);

        $week = app(ReportService::class)->weekRevenue();
        $this->assertEquals(100000.0, end($week['values']));

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('không gồm cọc');
    }
}
