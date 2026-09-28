<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Services\InventoryWriter;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private InventoryWriter $writer) {}

    public function index(): View
    {
        $rows = $this->skuRows();

        return view('admin.inventory.index', [
            'rows' => $rows,
            'showInspecting' => $rows->contains(fn (array $row) => $row['is_rental'] && $row['inspecting'] > 0),
            'showMaintenance' => $rows->contains(fn (array $row) => $row['is_rental'] && $row['maintenance'] > 0),
        ]);
    }

    public function upsertStock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0'],
            'rental_item_count' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);
        $variant = ProductVariant::query()->with('product')->findOrFail((int) $data['product_variant_id']);
        $mode = $variant->product?->offer_mode;
        $payload = [
            'product_variant_id' => $variant->id,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
        ];

        if ($mode === OfferMode::Sale || $mode === OfferMode::Both) {
            if ($data['quantity_on_hand'] === null) {
                return back()->withErrors(['quantity_on_hand' => 'Nhập tồn bán.']);
            }
            $payload['quantity_on_hand'] = (int) $data['quantity_on_hand'];
        }

        if ($mode === OfferMode::Rental) {
            $count = $data['rental_item_count'] ?? $data['quantity_on_hand'];
            if ($count === null) {
                return back()->withErrors(['rental_item_count' => 'Nhập số món thuê.']);
            }
            $payload['quantity_on_hand'] = (int) $count;
        }

        if ($mode === OfferMode::Both) {
            $payload['rental_item_count'] = $data['rental_item_count'];
            if ($payload['rental_item_count'] === null) {
                $payload['rental_item_count'] = $variant->items()->count();
            }
        }

        $this->writer->upsertStock($payload);

        return back()->with('success', 'Đã lưu số lượng kho.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->writer->createItem($request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'asset_code' => ['required', 'string', 'max:100', 'unique:inventory_items,asset_code'],
        ]));

        return back()->with('success', 'Đã thêm món thuê.');
    }

    public function changeItemStatus(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,rented,inspecting,maintenance'],
        ]);
        $this->writer->changeItemStatus($item, ItemStatus::from($data['status']));

        return back()->with('success', 'Đã đổi trạng thái món thuê.');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function skuRows(): Collection
    {
        $soldByVariant = OrderItem::query()
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as sold_qty')
            ->where('line_type', LineType::Sale)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [
                    OrderStatus::Paid,
                    OrderStatus::Processing,
                    OrderStatus::Completed,
                ]);
            })
            ->groupBy('product_variant_id')
            ->pluck('sold_qty', 'product_variant_id');

        return ProductVariant::query()
            ->with(['product', 'stock'])
            ->withCount([
                'items as items_total',
                'items as items_available' => fn ($query) => $query->where('status', ItemStatus::Available),
                'items as items_rented' => fn ($query) => $query->where('status', ItemStatus::Rented),
                'items as items_inspecting' => fn ($query) => $query->where('status', ItemStatus::Inspecting),
                'items as items_maintenance' => fn ($query) => $query->where('status', ItemStatus::Maintenance),
            ])
            ->orderBy('sku')
            ->get()
            ->map(function (ProductVariant $variant) use ($soldByVariant) {
                $mode = $variant->product?->offer_mode;
                $isRental = $mode === OfferMode::Rental || $mode === OfferMode::Both;
                $isSale = $mode === OfferMode::Sale || $mode === OfferMode::Both;

                return [
                    'variant' => $variant,
                    'mode' => $mode,
                    'is_rental' => $isRental,
                    'is_sale' => $isSale,
                    'rental_total' => (int) $variant->items_total,
                    'available' => (int) $variant->items_available,
                    'rented' => (int) $variant->items_rented,
                    'inspecting' => (int) $variant->items_inspecting,
                    'maintenance' => (int) $variant->items_maintenance,
                    'on_hand' => (int) ($variant->stock?->quantity_on_hand ?? 0),
                    'reserved' => (int) ($variant->stock?->quantity_reserved ?? 0),
                    'sold' => (int) ($soldByVariant[$variant->id] ?? 0),
                ];
            });
    }
}
