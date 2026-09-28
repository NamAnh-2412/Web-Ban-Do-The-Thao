<?php

namespace Tests;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function staffConfirmPendingPayments(int $orderId, ?User $staff = null): User
    {
        $previous = auth()->user();
        $staff ??= ($previous instanceof User && $previous->isAdmin()
            ? $previous
            : User::factory()->staff()->create());

        $this->actingAs($staff);
        foreach (Payment::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::Pending)
            ->orderBy('id')
            ->get() as $payment) {
            $this->post(route('admin.payments.confirm', $payment))->assertRedirect();
        }

        if ($previous instanceof User) {
            $this->actingAs($previous);
        }

        return $staff;
    }
}
