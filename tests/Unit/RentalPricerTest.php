<?php

namespace Tests\Unit;

use App\Domain\Rental\Services\RentalPricer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalPricerTest extends TestCase
{
    public function test_quote_counts_inclusive_days_and_keeps_deposit(): void
    {
        $quote = app(RentalPricer::class)->quote(
            '2026-09-01',
            '2026-09-03',
            80000,
            480000,
            300000,
        );

        $this->assertSame(3, $quote['days']);
        $this->assertSame(240000.0, $quote['rental_amount']);
        $this->assertSame(300000.0, $quote['deposit_amount']);
        $this->assertSame(540000.0, $quote['payable_now']);
    }

    public function test_week_rate_applies_when_renting_at_least_seven_days(): void
    {
        $amount = app(RentalPricer::class)->rentalAmount(10, 80000, 480000);

        $this->assertSame(720000.0, $amount);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(RentalPricer::class)->quote('2026-09-03', '2026-09-01', 80000, null, 0);
    }
}
