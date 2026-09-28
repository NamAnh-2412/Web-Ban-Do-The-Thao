<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryItemReservation;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Models\InventoryStockReservation;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\ReturnCondition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryWriter
{
    public function __construct(private AvailabilityService $availability) {}

    public function upsertStock(array $data): InventoryStock
    {
        $stock = InventoryStock::query()->updateOrCreate(
            ['product_variant_id' => $data['product_variant_id']],
            [
                'quantity_on_hand' => $data['quantity_on_hand'],
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
            ],
        );

        $variant = ProductVariant::query()->with('product')->find((int) $data['product_variant_id']);
        $mode = $variant?->product?->offer_mode;
        if ($mode === OfferMode::Rental && $this->variantNeedsRentalPool($variant)) {
            $this->syncRentalPool((int) $variant->id, (int) $data['quantity_on_hand']);
        }
        if ($mode === OfferMode::Both && array_key_exists('rental_item_count', $data) && $data['rental_item_count'] !== null) {
            $this->syncRentalPool((int) $variant->id, (int) $data['rental_item_count']);
        }

        return $stock;
    }

    public function createItem(array $data): InventoryItem
    {
        return InventoryItem::query()->create($data);
    }

    /**
     * Thuê khóa từng món (inventory_items), không dùng số tồn bán.
     * Ô "số lượng" trên form SP / kho sẽ tạo đủ món available.
     */
    public function syncRentalPool(int $variantId, int $desiredCount): void
    {
        $desiredCount = max(0, $desiredCount);
        $current = InventoryItem::query()->where('product_variant_id', $variantId)->count();

        while ($current < $desiredCount) {
            $this->createItem([
                'product_variant_id' => $variantId,
                'asset_code' => $this->uniqueAssetCode($variantId),
                'status' => ItemStatus::Available,
            ]);
            $current++;
        }

        if ($current <= $desiredCount) {
            return;
        }

        $extra = $current - $desiredCount;
        $removable = InventoryItem::query()
            ->where('product_variant_id', $variantId)
            ->where('status', ItemStatus::Available)
            ->whereDoesntHave('reservations', function ($query) {
                $query->whereIn('status', [
                    ReservationStatus::Pending->value,
                    ReservationStatus::Committed->value,
                ]);
            })
            ->orderByDesc('id')
            ->limit($extra)
            ->get();

        $removable->each->delete();
    }

    public function changeItemStatus(InventoryItem $item, ItemStatus $next, ?string $note = null): InventoryItem
    {
        if (! $item->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Không chuyển được từ {$item->status->value} sang {$next->value}."],
            ]);
        }

        $item->status = $next;
        if ($note !== null) {
            $item->condition_note = $note;
        }
        $item->save();

        return $item->refresh();
    }

    public function reserveSale(int $variantId, int $quantity, ?int $orderId = null): InventoryStockReservation
    {
        return DB::transaction(function () use ($variantId, $quantity, $orderId) {
            $stock = InventoryStock::query()
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($stock === null || $stock->availableQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Không đủ tồn kho bán.'],
                ]);
            }

            $stock->quantity_reserved += $quantity;
            $stock->save();

            return InventoryStockReservation::query()->create([
                'inventory_stock_id' => $stock->id,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => ReservationStatus::Pending,
                'expires_at' => now()->addMinutes(15),
            ]);
        });
    }

    public function reserveRental(int $variantId, string $startDate, string $endDate, ?int $orderId = null): InventoryItemReservation
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        return DB::transaction(function () use ($variantId, $start, $end, $orderId) {
            $item = $this->availability->freeItems($variantId, $start, $end)
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Không còn món thuê trống trong khoảng ngày này.'],
                ]);
            }

            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->first();

            $overlap = InventoryItemReservation::query()
                ->where('inventory_item_id', $locked->id)
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Committed->value])
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlap || $locked->status !== ItemStatus::Available) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Không còn món thuê trống trong khoảng ngày này.'],
                ]);
            }

            return InventoryItemReservation::query()->create([
                'inventory_item_id' => $locked->id,
                'order_id' => $orderId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => ReservationStatus::Pending,
            ]);
        });
    }

    public function commitSale(InventoryStockReservation $reservation): InventoryStockReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages(['status' => ['Reservation không còn pending.']]);
        }

        return DB::transaction(function () use ($reservation) {
            $stock = InventoryStock::query()->whereKey($reservation->inventory_stock_id)->lockForUpdate()->firstOrFail();
            $stock->quantity_on_hand = max(0, $stock->quantity_on_hand - $reservation->quantity);
            $stock->quantity_reserved = max(0, $stock->quantity_reserved - $reservation->quantity);
            $stock->save();

            $reservation->status = ReservationStatus::Committed;
            $reservation->save();

            return $reservation->refresh();
        });
    }

    public function releaseSale(InventoryStockReservation $reservation): InventoryStockReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages(['status' => ['Chỉ giải phóng reservation pending.']]);
        }

        return DB::transaction(function () use ($reservation) {
            $stock = InventoryStock::query()->whereKey($reservation->inventory_stock_id)->lockForUpdate()->firstOrFail();
            $stock->quantity_reserved = max(0, $stock->quantity_reserved - $reservation->quantity);
            $stock->save();

            $reservation->status = ReservationStatus::Released;
            $reservation->save();

            return $reservation->refresh();
        });
    }

    public function restoreCommittedSale(InventoryStockReservation $reservation): InventoryStockReservation
    {
        if ($reservation->status !== ReservationStatus::Committed) {
            throw ValidationException::withMessages(['status' => ['Chỉ hoàn tồn reservation đã trừ kho.']]);
        }

        return DB::transaction(function () use ($reservation) {
            $stock = InventoryStock::query()->whereKey($reservation->inventory_stock_id)->lockForUpdate()->firstOrFail();
            $stock->quantity_on_hand += $reservation->quantity;
            $stock->save();

            $reservation->status = ReservationStatus::Released;
            $reservation->save();

            return $reservation->refresh();
        });
    }

    public function commitRental(InventoryItemReservation $reservation): InventoryItemReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages(['status' => ['Reservation không còn pending.']]);
        }

        $item = $reservation->item;
        $this->changeItemStatus($item, ItemStatus::Rented);
        $reservation->status = ReservationStatus::Committed;
        $reservation->save();

        return $reservation->refresh();
    }

    /**
     * Nhân viên xác nhận khách đã trả: đưa món về kho (nguyên vẹn) hoặc bảo trì (hư/mất).
     */
    public function completeRentalReturn(InventoryItem $item, ReturnCondition $condition): InventoryItem
    {
        if ($item->status === ItemStatus::Rented) {
            $item = $this->changeItemStatus($item, ItemStatus::Inspecting, 'Nhận trả đồ.');
        }

        if ($item->status !== ItemStatus::Inspecting) {
            return $item;
        }

        return match ($condition) {
            ReturnCondition::Good => $this->changeItemStatus($item, ItemStatus::Available, 'Đồ nguyên vẹn, cho thuê lại.'),
            ReturnCondition::Damaged, ReturnCondition::Lost => $this->changeItemStatus(
                $item,
                ItemStatus::Maintenance,
                $condition === ReturnCondition::Lost ? 'Mất món thuê.' : 'Hư hỏng khi trả.',
            ),
        };
    }

    public function releaseRental(InventoryItemReservation $reservation): InventoryItemReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages(['status' => ['Chỉ giải phóng reservation pending.']]);
        }

        $reservation->status = ReservationStatus::Released;
        $reservation->save();

        return $reservation->refresh();
    }

    public function restoreCommittedRental(InventoryItemReservation $reservation): InventoryItemReservation
    {
        if ($reservation->status !== ReservationStatus::Committed) {
            throw ValidationException::withMessages(['status' => ['Chỉ hoàn món thuê đã khóa.']]);
        }

        $reservation->loadMissing('item');
        $item = $reservation->item;
        if ($item !== null && $item->status === ItemStatus::Rented) {
            $item->status = ItemStatus::Available;
            $item->condition_note = 'Hủy đơn trước giao đồ.';
            $item->save();
        }

        $reservation->status = ReservationStatus::Released;
        $reservation->save();

        return $reservation->refresh();
    }

    public function releaseByOrder(int $orderId): void
    {
        InventoryStockReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Pending)
            ->get()
            ->each(fn (InventoryStockReservation $row) => $this->releaseSale($row));

        InventoryStockReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Committed)
            ->get()
            ->each(fn (InventoryStockReservation $row) => $this->restoreCommittedSale($row));

        InventoryItemReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Pending)
            ->get()
            ->each(fn (InventoryItemReservation $row) => $this->releaseRental($row));

        InventoryItemReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Committed)
            ->get()
            ->each(fn (InventoryItemReservation $row) => $this->restoreCommittedRental($row));
    }

    public function commitByOrder(int $orderId): void
    {
        InventoryStockReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Pending)
            ->get()
            ->each(fn (InventoryStockReservation $row) => $this->commitSale($row));

        InventoryItemReservation::query()
            ->where('order_id', $orderId)
            ->where('status', ReservationStatus::Pending)
            ->get()
            ->each(fn (InventoryItemReservation $row) => $this->commitRental($row));
    }

    private function variantNeedsRentalPool(?ProductVariant $variant): bool
    {
        if ($variant === null || $variant->rental_price_per_day === null) {
            return false;
        }

        $mode = $variant->product?->offer_mode;

        return $mode === OfferMode::Rental || $mode === OfferMode::Both;
    }

    private function uniqueAssetCode(int $variantId): string
    {
        $sku = ProductVariant::query()->whereKey($variantId)->value('sku');
        $base = strtoupper((string) ($sku ?: 'RENT-'.$variantId));
        $n = InventoryItem::query()->where('product_variant_id', $variantId)->count() + 1;

        do {
            $code = $base.'-'.$n;
            $n++;
        } while (InventoryItem::query()->where('asset_code', $code)->exists());

        return $code;
    }
}
