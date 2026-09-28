<?php

namespace App\Domain\Rental\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RentalPricer
{
    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     days: int,
     *     daily_rate: float,
     *     weekly_rate: float|null,
     *     rental_amount: float,
     *     deposit_amount: float,
     *     payable_now: float
     * }
     */
    public function quote(
        string $startDate,
        string $endDate,
        float|string $dailyRate,
        float|string|null $weeklyRate,
        float|string $depositAmount,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'],
            ]);
        }

        $days = (int) $start->diffInDays($end) + 1;
        $daily = round((float) $dailyRate, 2);
        $weekly = $weeklyRate === null || $weeklyRate === '' ? null : round((float) $weeklyRate, 2);
        $deposit = round((float) $depositAmount, 2);

        if ($daily < 0 || $deposit < 0 || ($weekly !== null && $weekly < 0)) {
            throw ValidationException::withMessages([
                'daily_rate' => ['Giá thuê không hợp lệ.'],
            ]);
        }

        $rentalAmount = $this->rentalAmount($days, $daily, $weekly);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'daily_rate' => $daily,
            'weekly_rate' => $weekly,
            'rental_amount' => $rentalAmount,
            'deposit_amount' => $deposit,
            'payable_now' => round($rentalAmount + $deposit, 2),
        ];
    }

    public function rentalAmount(int $days, float $dailyRate, ?float $weeklyRate): float
    {
        $days = max(1, $days);

        if ($weeklyRate !== null && $weeklyRate > 0 && $days >= 7) {
            $weeks = intdiv($days, 7);
            $rest = $days % 7;

            return round(($weeks * $weeklyRate) + ($rest * $dailyRate), 2);
        }

        return round($days * $dailyRate, 2);
    }
}
