<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Models\StockReceipt;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_receipt_waits_for_admin_approval_before_adding_stock(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->variant(OfferMode::Sale, 'NHAP-BAN');
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 4,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Phiếu nhập kho')
            ->assertDontSee('Cập nhật số lượng');

        $this->actingAs($staff)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 99,
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('admin.inventory.receipts.store'), [
            'note' => 'Hàng về sáng',
            'lines' => [
                ['product_variant_id' => $variant->id, 'sale_quantity' => 10],
            ],
        ])->assertRedirect();

        $this->assertSame(4, $variant->fresh()->stock->quantity_on_hand);
        $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Phiếu nhập chờ duyệt')
            ->assertSee('+10 bán')
            ->assertSee('Hàng về sáng')
            ->assertSee('Cập nhật số lượng');

        $receiptId = StockReceipt::query()->value('id');
        $this->actingAs($staff)
            ->post(route('admin.inventory.receipts.approve', $receiptId))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.inventory.receipts.approve', $receiptId))
            ->assertRedirect();

        $this->assertSame(14, $variant->fresh()->stock->quantity_on_hand);
    }

    public function test_rejected_receipt_does_not_change_rental_items(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->variant(OfferMode::Rental, 'NHAP-THUE');
        InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'NHAP-THUE-1',
        ]);

        $this->actingAs($staff)->post(route('admin.inventory.receipts.store'), [
            'lines' => [
                ['product_variant_id' => $variant->id, 'rental_quantity' => 2],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $variant->items()->count());

        $this->actingAs($admin)
            ->post(route('admin.inventory.receipts.reject', StockReceipt::query()->value('id')))
            ->assertRedirect();

        $this->assertSame(1, $variant->items()->count());
    }

    public function test_approving_both_receipt_adds_each_pool(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->variant(OfferMode::Both, 'NHAP-CA');
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 3,
        ]);
        InventoryItem::factory()->create([
            'product_variant_id' => $variant->id,
            'asset_code' => 'NHAP-CA-1',
        ]);

        $this->actingAs($admin)->post(route('admin.inventory.receipts.store'), [
            'lines' => [
                ['product_variant_id' => $variant->id, 'sale_quantity' => 5, 'rental_quantity' => 2],
            ],
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('admin.inventory.receipts.store'), [
            'lines' => [
                ['product_variant_id' => $variant->id, 'sale_quantity' => 5, 'rental_quantity' => 2],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.inventory.receipts.approve', StockReceipt::query()->value('id')))
            ->assertRedirect();

        $this->assertSame(8, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(3, $variant->items()->count());
    }

    public function test_one_receipt_includes_several_products(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $sale = $this->variant(OfferMode::Sale, 'NHAP-1');
        $rent = $this->variant(OfferMode::Rental, 'NHAP-2');
        InventoryStock::factory()->create([
            'product_variant_id' => $sale->id,
            'quantity_on_hand' => 1,
        ]);

        $this->actingAs($staff)->post(route('admin.inventory.receipts.store'), [
            'lines' => [
                ['product_variant_id' => $sale->id, 'sale_quantity' => 4],
                ['product_variant_id' => $rent->id, 'rental_quantity' => 3],
            ],
        ])->assertRedirect();

        $this->assertSame(1, StockReceipt::query()->count());
        $this->assertSame(1, $sale->fresh()->stock->quantity_on_hand);
        $this->assertSame(0, $rent->items()->count());

        $this->actingAs($admin)
            ->post(route('admin.inventory.receipts.approve', StockReceipt::query()->value('id')))
            ->assertRedirect();

        $this->assertSame(5, $sale->fresh()->stock->quantity_on_hand);
        $this->assertSame(3, $rent->items()->count());
    }

    private function variant(OfferMode $mode, string $sku): ProductVariant
    {
        $product = Product::factory()->create([
            'offer_mode' => $mode,
            'name' => $sku,
            'slug' => strtolower($sku),
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'sale_price' => $mode === OfferMode::Rental ? null : 100000,
            'rental_price_per_day' => $mode === OfferMode::Sale ? null : 20000,
        ]);
    }
}
