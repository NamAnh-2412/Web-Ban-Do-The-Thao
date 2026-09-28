<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryItemReservation;
use App\Domain\Inventory\Models\InventoryStock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function saleAvailability(int $variantId): array
    {
        $stock = InventoryStock::query()->where('product_variant_id', $variantId)->first();

        if ($stock === null) {
            return [
                'product_variant_id' => $variantId,
                'available' => false,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'is_low' => true,
            ];
        }

        $available = $stock->availableQuantity();

        return [
            'product_variant_id' => $variantId,
            'available' => $available > 0,
            'quantity_on_hand' => $stock->quantity_on_hand,
            'quantity_reserved' => $stock->quantity_reserved,
            'quantity_available' => $available,
            'is_low' => $stock->isLow(),
        ];
    }

    public function rentalAvailability(int $variantId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'],
            ]);
        }

        $items = $this->freeItems($variantId, $start, $end);

        return [
            'product_variant_id' => $variantId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'available' => $items->isNotEmpty(),
            'quantity_available' => $items->count(),
            'items' => $items->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'asset_code' => $item->asset_code,
                'status' => $item->status->value,
            ])->values()->all(),
        ];
    }

    /** @return Collection<int, InventoryItem> */
    public function freeItems(int $variantId, Carbon $start, Carbon $end): Collection
    {
        return InventoryItem::query()
            ->where('product_variant_id', $variantId)
            ->where('status', ItemStatus::Available)
            ->whereDoesntHave('reservations', function ($q) use ($start, $end) {
                $q->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Committed->value])
                    ->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->orderBy('asset_code')
            ->get();
    }

    public function itemWindow(int $itemId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => ['Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'],
            ]);
        }

        $item = InventoryItem::query()->find($itemId);

        if ($item === null) {
            return [
                'inventory_item_id' => $itemId,
                'available' => false,
                'window_clear' => false,
                'status' => null,
            ];
        }

        $overlap = InventoryItemReservation::query()
            ->where('inventory_item_id', $item->id)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Committed->value])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        return [
            'inventory_item_id' => $item->id,
            'available' => $item->status === ItemStatus::Available && ! $overlap,
            'window_clear' => ! $overlap,
            'status' => $item->status->value,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }
}
