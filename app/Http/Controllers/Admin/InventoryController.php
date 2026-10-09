<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Enums\StockReceiptStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\StockReceipt;
use App\Domain\Inventory\Services\InventoryWriter;
use App\Domain\Inventory\Services\StockReceiptWriter;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Product\Support\CatalogCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryWriter $writer,
        private StockReceiptWriter $receipts,
    ) {}

    public function index(Request $request): View
    {
        $offerMode = $request->query('offer_mode');
        $offerMode = is_string($offerMode) && OfferMode::tryFrom($offerMode) !== null
            ? $offerMode
            : '';

        $rows = $this->skuRows();
        if ($offerMode !== '') {
            $rows = $rows
                ->filter(fn (array $row) => $row['mode']?->value === $offerMode)
                ->values();
        }

        $user = $request->user();
        $receipts = StockReceipt::query()
            ->with(['lines.variant.product', 'creator'])
            ->when($user?->isOwnerAdmin(), fn ($query) => $query->where('status', StockReceiptStatus::Pending))
            ->when($user && ! $user->isOwnerAdmin(), fn ($query) => $query->where('created_by', $user->id))
            ->latest('id')
            ->limit(30)
            ->get();

        return view('admin.inventory.index', [
            'rows' => $rows,
            'offerMode' => $offerMode,
            'showInspecting' => $rows->contains(fn (array $row) => $row['is_rental'] && $row['inspecting'] > 0),
            'showMaintenance' => $rows->contains(fn (array $row) => $row['is_rental'] && $row['maintenance'] > 0),
            'receipts' => $receipts,
        ]);
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isOwnerAdmin() === true, 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.sale_quantity' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'lines.*.rental_quantity' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $line) {
            $lines[] = [
                'product_variant_id' => (int) $line['product_variant_id'],
                'sale_quantity' => (int) ($line['sale_quantity'] ?? 0),
                'rental_quantity' => (int) ($line['rental_quantity'] ?? 0),
            ];
        }

        $this->receipts->submit($request->user(), $lines, $data['note'] ?? null);

        return back()->with('success', 'Đã gửi phiếu nhập kho. Số lượng chỉ cộng sau khi quản trị duyệt.');
    }

    public function approveReceipt(Request $request, StockReceipt $receipt): RedirectResponse
    {
        abort_unless($request->user()?->isOwnerAdmin() === true, 403);
        $this->receipts->approve($receipt, $request->user());

        return back()->with('success', 'Đã duyệt phiếu và cập nhật số lượng kho.');
    }

    public function rejectReceipt(Request $request, StockReceipt $receipt): RedirectResponse
    {
        abort_unless($request->user()?->isOwnerAdmin() === true, 403);
        $this->receipts->reject($receipt, $request->user());

        return back()->with('success', 'Đã từ chối phiếu nhập kho.');
    }

    public function toggleSuspension(Product $product): RedirectResponse
    {
        $product->is_suspended = ! $product->is_suspended;
        $product->save();
        CatalogCache::bump();

        return back()->with('success', $product->is_suspended
            ? 'Đã tạm khóa sản phẩm. Khách không còn thấy trên cửa hàng.'
            : 'Đã mở lại sản phẩm trên cửa hàng.');
    }

    public function upsertStock(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOwnerAdmin() === true, 403);

        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0'],
            'rental_item_count' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);
        $variant = ProductVariant::query()->with(['product', 'stock'])->findOrFail((int) $data['product_variant_id']);
        $mode = $variant->product?->offer_mode;
        $onHand = $data['quantity_on_hand'] ?? null;
        $rentalCount = $data['rental_item_count'] ?? null;
        $payload = [
            'product_variant_id' => $variant->id,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
        ];

        if ($mode === OfferMode::Sale) {
            if ($onHand === null) {
                return back()->withErrors(['quantity_on_hand' => 'Sản phẩm chỉ bán. Nhập tồn bán.'])->withInput();
            }
            $payload['quantity_on_hand'] = (int) $onHand;
        } elseif ($mode === OfferMode::Rental) {
            if ($rentalCount === null) {
                return back()->withErrors(['rental_item_count' => 'Sản phẩm chỉ thuê. Nhập số món thuê.'])->withInput();
            }
            $payload['quantity_on_hand'] = (int) $rentalCount;
        } elseif ($mode === OfferMode::Both) {
            if ($onHand === null && $rentalCount === null) {
                return back()->withErrors(['quantity_on_hand' => 'Nhập tồn bán hoặc số món thuê.'])->withInput();
            }
            $payload['quantity_on_hand'] = $onHand === null
                ? (int) ($variant->stock?->quantity_on_hand ?? 0)
                : (int) $onHand;
            if ($rentalCount !== null) {
                $payload['rental_item_count'] = (int) $rentalCount;
            }
        } else {
            return back()->withErrors(['product_variant_id' => 'Không xác định được hình thức bán.'])->withInput();
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
