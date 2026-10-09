<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\StockReceiptStatus;
use App\Domain\Inventory\Models\StockReceipt;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReceiptWriter
{
    public function __construct(private InventoryWriter $inventory) {}

    /** @param list<array{product_variant_id: int, sale_quantity: int, rental_quantity: int}> $lines */
    public function submit(User $staff, array $lines, ?string $note): StockReceipt
    {
        $prepared = [];
        $seen = [];
        foreach ($lines as $index => $line) {
            $variantId = (int) $line['product_variant_id'];
            if (isset($seen[$variantId])) {
                throw ValidationException::withMessages([
                    "lines.$index.product_variant_id" => 'Mỗi sản phẩm chỉ xuất hiện một lần trong phiếu.',
                ]);
            }
            $seen[$variantId] = true;
            $prepared[] = $this->prepareLine($variantId, (int) $line['sale_quantity'], (int) $line['rental_quantity'], $index);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'lines' => 'Phiếu cần ít nhất một sản phẩm.',
            ]);
        }

        return DB::transaction(function () use ($staff, $note, $prepared) {
            $receipt = StockReceipt::query()->create([
                'note' => $note,
                'status' => StockReceiptStatus::Pending,
                'created_by' => $staff->id,
            ]);
            $receipt->lines()->createMany($prepared);

            return $receipt;
        });
    }

    public function approve(StockReceipt $receipt, User $admin): void
    {
        DB::transaction(function () use ($receipt, $admin) {
            $locked = StockReceipt::query()->whereKey($receipt->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== StockReceiptStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu này đã được xử lý.',
                ]);
            }

            $locked->load('lines');
            foreach ($locked->lines as $line) {
                $this->applyLine($line->product_variant_id, (int) $line->sale_quantity, (int) $line->rental_quantity);
            }

            $locked->status = StockReceiptStatus::Approved;
            $locked->reviewed_by = $admin->id;
            $locked->reviewed_at = now();
            $locked->save();
        });
    }

    public function reject(StockReceipt $receipt, User $admin): void
    {
        DB::transaction(function () use ($receipt, $admin) {
            $locked = StockReceipt::query()->whereKey($receipt->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== StockReceiptStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu này đã được xử lý.',
                ]);
            }

            $locked->status = StockReceiptStatus::Rejected;
            $locked->reviewed_by = $admin->id;
            $locked->reviewed_at = now();
            $locked->save();
        });
    }

    /** @return array{product_variant_id: int, sale_quantity: int, rental_quantity: int} */
    private function prepareLine(int $variantId, int $saleQuantity, int $rentalQuantity, int $index): array
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
        $mode = $variant->product?->offer_mode;
        $saleQuantity = max(0, $saleQuantity);
        $rentalQuantity = max(0, $rentalQuantity);

        if ($mode === OfferMode::Sale) {
            $rentalQuantity = 0;
            if ($saleQuantity < 1) {
                throw ValidationException::withMessages([
                    "lines.$index.sale_quantity" => 'Sản phẩm chỉ bán. Nhập số tồn bán cần thêm.',
                ]);
            }
        } elseif ($mode === OfferMode::Rental) {
            $saleQuantity = 0;
            if ($rentalQuantity < 1) {
                throw ValidationException::withMessages([
                    "lines.$index.rental_quantity" => 'Sản phẩm chỉ thuê. Nhập số món thuê cần thêm.',
                ]);
            }
        } elseif ($mode === OfferMode::Both) {
            if ($saleQuantity < 1 && $rentalQuantity < 1) {
                throw ValidationException::withMessages([
                    "lines.$index.sale_quantity" => 'Nhập số tồn bán hoặc số món thuê cần thêm.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                "lines.$index.product_variant_id" => 'Không xác định được hình thức bán.',
            ]);
        }

        return [
            'product_variant_id' => $variant->id,
            'sale_quantity' => $saleQuantity,
            'rental_quantity' => $rentalQuantity,
        ];
    }

    private function applyLine(int $variantId, int $saleQuantity, int $rentalQuantity): void
    {
        $variant = ProductVariant::query()->with(['product', 'stock'])->findOrFail($variantId);
        $mode = $variant->product?->offer_mode;
        $onHand = (int) ($variant->stock?->quantity_on_hand ?? 0);
        $rentalCount = $variant->items()->count();
        $payload = [
            'product_variant_id' => $variant->id,
            'low_stock_threshold' => (int) ($variant->stock?->low_stock_threshold ?? 5),
        ];

        if ($mode === OfferMode::Sale) {
            $payload['quantity_on_hand'] = $onHand + $saleQuantity;
        } elseif ($mode === OfferMode::Rental) {
            $payload['quantity_on_hand'] = $rentalCount + $rentalQuantity;
        } elseif ($mode === OfferMode::Both) {
            $payload['quantity_on_hand'] = $onHand + $saleQuantity;
            if ($rentalQuantity > 0) {
                $payload['rental_item_count'] = $rentalCount + $rentalQuantity;
            }
        }

        $this->inventory->upsertStock($payload);
    }
}
