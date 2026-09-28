<?php

namespace App\Domain\Rental\Services;

use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryItemReservation;
use App\Domain\Inventory\Services\InventoryWriter;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Enums\ExtensionStatus;
use App\Domain\Rental\Enums\IncidentType;
use App\Domain\Rental\Enums\ReturnCondition;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Models\RentalExtension;
use App\Domain\Rental\Models\RentalIncident;
use App\Domain\Rental\Models\RentalReturn;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalWriter
{
    public function __construct(
        private RentalPricer $pricer,
        private InventoryWriter $inventory,
    ) {}

    public function createBooking(int $userId, array $data): RentalBooking
    {
        $quote = $this->pricer->quote(
            $data['start_date'],
            $data['end_date'],
            $data['daily_rate'],
            $data['weekly_rate'] ?? null,
            $data['deposit_amount'],
        );

        $this->assertItemFree(
            (int) $data['inventory_item_id'],
            $quote['start_date'],
            $quote['end_date'],
        );

        return RentalBooking::query()->create([
            'order_id' => $data['order_id'],
            'order_item_id' => $data['order_item_id'],
            'user_id' => $userId,
            'inventory_item_id' => $data['inventory_item_id'],
            'product_variant_id' => $data['product_variant_id'],
            'start_date' => $quote['start_date'],
            'end_date' => $quote['end_date'],
            'daily_rate' => $quote['daily_rate'],
            'rental_amount' => $quote['rental_amount'],
            'deposit_amount' => $quote['deposit_amount'],
            'status' => BookingStatus::Pending,
        ]);
    }

    public function changeStatus(RentalBooking $booking, BookingStatus $next): RentalBooking
    {
        if (! $booking->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Không chuyển được từ {$booking->status->value} sang {$next->value}."],
            ]);
        }

        $booking->status = $next;
        $booking->save();

        return $booking->refresh();
    }

    public function cancel(RentalBooking $booking): RentalBooking
    {
        return $this->changeStatus($booking, BookingStatus::Cancelled);
    }

    public function confirm(RentalBooking $booking): RentalBooking
    {
        return $this->changeStatus($booking, BookingStatus::Confirmed);
    }

    public function activate(RentalBooking $booking): RentalBooking
    {
        return $this->changeStatus($booking, BookingStatus::Active);
    }

    public function markOverdue(): int
    {
        return RentalBooking::query()
            ->where('status', BookingStatus::Active)
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => BookingStatus::Overdue->value]);
    }

    /** @return Collection<int, RentalBooking> */
    public function dueOn(string $date): Collection
    {
        return RentalBooking::query()
            ->whereDate('end_date', $date)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Active->value])
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, RentalBooking> */
    public function listOverdue(): Collection
    {
        return RentalBooking::query()
            ->where('status', BookingStatus::Overdue)
            ->orderBy('end_date')
            ->get();
    }

    public function requestExtensionForSession(RentalBooking $booking, string $newEndDate): void
    {
        foreach (RentalSession::siblings($booking) as $sibling) {
            if (! in_array($sibling->status, [BookingStatus::Confirmed, BookingStatus::Active], true)) {
                continue;
            }
            if ($sibling->extensions->contains(fn ($extension) => $extension->status === ExtensionStatus::Pending)) {
                continue;
            }
            $this->requestExtension($sibling, $newEndDate);
        }
    }

    public function requestExtension(RentalBooking $booking, string $newEndDate): RentalExtension
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Active], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ gia hạn booking đã xác nhận hoặc đang thuê.'],
            ]);
        }

        $newEnd = Carbon::parse($newEndDate)->startOfDay();
        $oldEnd = $booking->end_date->copy()->startOfDay();

        if (! $newEnd->gt($oldEnd)) {
            throw ValidationException::withMessages([
                'new_end_date' => ['Ngày trả mới phải sau ngày trả hiện tại.'],
            ]);
        }

        $pending = $booking->extensions()
            ->where('status', ExtensionStatus::Pending)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'new_end_date' => ['Đang có yêu cầu gia hạn chờ duyệt.'],
            ]);
        }

        $extraStart = $oldEnd->copy()->addDay();
        $quote = $this->pricer->quote(
            $extraStart->toDateString(),
            $newEnd->toDateString(),
            (float) $booking->daily_rate,
            null,
            0,
        );

        $this->assertItemFree(
            $booking->inventory_item_id,
            $extraStart->toDateString(),
            $newEnd->toDateString(),
            $booking->id,
        );

        return $booking->extensions()->create([
            'old_end_date' => $oldEnd->toDateString(),
            'new_end_date' => $newEnd->toDateString(),
            'extra_amount' => $quote['rental_amount'],
            'status' => ExtensionStatus::Pending,
        ]);
    }

    public function approveExtension(RentalExtension $extension): RentalExtension
    {
        if ($extension->status !== ExtensionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Yêu cầu gia hạn không còn pending.'],
            ]);
        }

        return DB::transaction(function () use ($extension) {
            $booking = RentalBooking::query()->whereKey($extension->rental_booking_id)->lockForUpdate()->firstOrFail();
            $extraStart = $extension->old_end_date->copy()->addDay();

            $this->assertItemFree(
                $booking->inventory_item_id,
                $extraStart->toDateString(),
                $extension->new_end_date->toDateString(),
                $booking->id,
            );

            $booking->end_date = $extension->new_end_date;
            $booking->rental_amount = round((float) $booking->rental_amount + (float) $extension->extra_amount, 2);
            $booking->save();

            InventoryItemReservation::query()
                ->where('inventory_item_id', $booking->inventory_item_id)
                ->where('order_id', $booking->order_id)
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Committed->value])
                ->update(['end_date' => $extension->new_end_date->toDateString()]);

            $extension->status = ExtensionStatus::Approved;
            $extension->save();

            return $extension->refresh();
        });
    }

    public function rejectExtension(RentalExtension $extension): RentalExtension
    {
        if ($extension->status !== ExtensionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Yêu cầu gia hạn không còn pending.'],
            ]);
        }

        $extension->status = ExtensionStatus::Rejected;
        $extension->save();

        return $extension->refresh();
    }

    public function returnBooking(
        RentalBooking $booking,
        int $staffUserId,
        ReturnCondition $condition,
        ?string $notes = null,
        ?string $incidentDescription = null,
        ?float $incidentFee = null,
    ): RentalBooking {
        if (! in_array($booking->status, [BookingStatus::Active, BookingStatus::Overdue], true)) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ trả đồ khi đang thuê hoặc quá hạn.'],
            ]);
        }

        if ($booking->rentalReturn()->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Booking này đã có biên bản trả.'],
            ]);
        }

        return DB::transaction(function () use (
            $booking,
            $staffUserId,
            $condition,
            $notes,
            $incidentDescription,
            $incidentFee,
        ) {
            $returnedAt = now();
            $return = RentalReturn::query()->create([
                'rental_booking_id' => $booking->id,
                'returned_at' => $returnedAt,
                'staff_user_id' => $staffUserId,
                'condition' => $condition,
                'notes' => $notes,
            ]);

            $end = $booking->end_date->copy()->startOfDay();
            $returnedDay = $returnedAt->copy()->startOfDay();
            if ($returnedDay->gt($end)) {
                $lateDays = (int) $end->diffInDays($returnedDay);
                $fee = round($lateDays * (float) $booking->daily_rate, 2);
                RentalIncident::query()->create([
                    'rental_booking_id' => $booking->id,
                    'rental_return_id' => $return->id,
                    'type' => IncidentType::Late,
                    'description' => "Trả muộn {$lateDays} ngày.",
                    'fee_amount' => $fee,
                ]);
            }

            if ($condition === ReturnCondition::Damaged) {
                RentalIncident::query()->create([
                    'rental_booking_id' => $booking->id,
                    'rental_return_id' => $return->id,
                    'type' => IncidentType::Damage,
                    'description' => $incidentDescription ?: 'Hư hỏng khi trả đồ.',
                    'fee_amount' => round((float) ($incidentFee ?? 0), 2),
                ]);
            }

            if ($condition === ReturnCondition::Lost) {
                $lostFee = ($incidentFee === null || $incidentFee <= 0)
                    ? (float) $booking->deposit_amount
                    : $incidentFee;
                RentalIncident::query()->create([
                    'rental_booking_id' => $booking->id,
                    'rental_return_id' => $return->id,
                    'type' => IncidentType::Lost,
                    'description' => $incidentDescription ?: 'Mất món thuê.',
                    'fee_amount' => round((float) $lostFee, 2),
                ]);
            }

            $booking->status = BookingStatus::Returned;
            $booking->save();

            $item = InventoryItem::query()->find($booking->inventory_item_id);
            if ($item !== null) {
                $this->inventory->completeRentalReturn($item, $condition);
            }

            return $booking->refresh()->load(['extensions', 'rentalReturn', 'incidents']);
        });
    }

    public function addIncident(
        RentalBooking $booking,
        IncidentType $type,
        float $feeAmount,
        ?string $description = null,
        ?int $returnId = null,
    ): RentalIncident {
        if (in_array($booking->status, [BookingStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => ['Không ghi sự cố cho booking đã hủy.'],
            ]);
        }

        return RentalIncident::query()->create([
            'rental_booking_id' => $booking->id,
            'rental_return_id' => $returnId,
            'type' => $type,
            'description' => $description,
            'fee_amount' => round($feeAmount, 2),
        ]);
    }

    public function depositOutcome(RentalBooking $booking): array
    {
        $fees = $booking->relationLoaded('incidents')
            ? (float) $booking->incidents->sum('fee_amount')
            : (float) $booking->incidents()->sum('fee_amount');
        $deposit = (float) $booking->deposit_amount;
        $refund = round(max(0, $deposit - $fees), 2);
        $extraDue = round(max(0, $fees - $deposit), 2);

        return [
            'deposit_amount' => $deposit,
            'fees_total' => round($fees, 2),
            'refund_suggested' => $refund,
            'extra_due' => $extraDue,
            'note' => $extraDue > 0
                ? 'Phí vượt cọc. Cửa hàng thu thêm phần đền bù.'
                : 'Hoàn cọc còn lại sau khi trừ phí sự cố (hỏng / mất / trễ).',
        ];
    }

    public function confirmByOrder(int $orderId): void
    {
        RentalBooking::query()
            ->where('order_id', $orderId)
            ->where('status', BookingStatus::Pending)
            ->get()
            ->each(fn (RentalBooking $booking) => $this->confirm($booking));
    }

    public function activateByOrder(int $orderId): void
    {
        RentalBooking::query()
            ->where('order_id', $orderId)
            ->where('status', BookingStatus::Confirmed)
            ->get()
            ->each(fn (RentalBooking $booking) => $this->activate($booking));
    }

    public function cancelByOrder(int $orderId): void
    {
        RentalBooking::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->get()
            ->each(function (RentalBooking $booking) {
                if ($booking->status->canTransitionTo(BookingStatus::Cancelled)) {
                    $this->cancel($booking);
                }
            });
    }

    private function assertItemFree(int $inventoryItemId, string $start, string $end, ?int $ignoreBookingId = null): void
    {
        $overlap = RentalBooking::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Active->value,
                BookingStatus::Overdue->value,
            ])
            ->when($ignoreBookingId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'inventory_item_id' => ['Món này đã có lịch thuê trùng khoảng ngày.'],
            ]);
        }
    }
}
