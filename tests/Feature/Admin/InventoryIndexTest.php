<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_index_shows_sku_totals_not_each_asset(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt kho tổng hợp',
            'slug' => 'vot-kho-tong-hop',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'VOT-KHO-SKU',
            'sale_price' => null,
            'rental_price_per_day' => 80000,
        ]);

        foreach ([1, 2, 3] as $n) {
            InventoryItem::factory()->create([
                'product_variant_id' => $variant->id,
                'asset_code' => 'VOT-KHO-SKU-'.$n,
            ]);
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Vợt kho tổng hợp')
            ->assertSee('VOT-KHO-SKU')
            ->assertSee('3')
            ->assertDontSee('VOT-KHO-SKU-1')
            ->assertDontSee('VOT-KHO-SKU-2')
            ->assertDontSee('VOT-KHO-SKU-3')
            ->assertDontSee('Thêm món thuê')
            ->assertDontSee('Mã món')
            ->assertSee('data-mode="rental"', false)
            ->assertSee('js/admin/inventory-form.js', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/>Thuê</', $html);
        $this->assertMatchesRegularExpression('/>Bán</', $html);
    }

    public function test_inventory_index_counts_sold_from_settled_sale_orders_only(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo đã bán kho',
            'slug' => 'ao-da-ban-kho',
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'AO-SOLD-SKU',
            'sale_price' => 100000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
        ]);

        $paid = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->create([
            'order_id' => $paid->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'line_type' => LineType::Sale,
            'quantity' => 17,
            'line_total' => 1700000,
        ]);

        $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $pending->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'line_type' => LineType::Sale,
            'quantity' => 9,
            'line_total' => 900000,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Áo đã bán kho')
            ->getContent();

        $this->assertMatchesRegularExpression('/AO-SOLD-SKU[\s\S]{0,1600}?>17</', $html);
        $this->assertDoesNotMatchRegularExpression('/AO-SOLD-SKU[\s\S]{0,1600}?>26</', $html);
        $this->assertDoesNotMatchRegularExpression('/AO-SOLD-SKU[\s\S]{0,1600}?>9</', $html);
    }

    public function test_inventory_index_filters_by_offer_mode(): void
    {
        $admin = User::factory()->admin()->create();
        $sale = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo chỉ bán lọc',
            'slug' => 'ao-chi-ban-loc',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $sale->id,
            'sku' => 'LOC-SALE',
            'sale_price' => 100000,
            'rental_price_per_day' => null,
        ]);
        $both = Product::factory()->create([
            'offer_mode' => OfferMode::Both,
            'name' => 'Áo bán và thuê lọc',
            'slug' => 'ao-ban-va-thue-loc',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $both->id,
            'sku' => 'LOC-BOTH',
            'sale_price' => 100000,
            'rental_price_per_day' => 20000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['offer_mode' => 'both']))
            ->assertOk()
            ->assertSee('LOC-BOTH')
            ->assertDontSee('LOC-SALE')
            ->assertSee('Bán và thuê');

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['offer_mode' => 'sale']))
            ->assertOk()
            ->assertSee('LOC-SALE')
            ->assertDontSee('LOC-BOTH');
    }

    public function test_rental_only_stock_ignores_sale_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variantWithMode(OfferMode::Rental, 'RENT-POOL');
        foreach (range(1, 20) as $n) {
            InventoryItem::factory()->create([
                'product_variant_id' => $variant->id,
                'asset_code' => 'RENT-POOL-'.$n,
            ]);
        }

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 10,
        ])->assertRedirect()
            ->assertSessionHasErrors('rental_item_count');

        $this->assertSame(20, $variant->items()->count());

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 10,
            'rental_item_count' => 20,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $variant->items()->count());
    }

    public function test_sale_only_stock_ignores_rental_count(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variantWithMode(OfferMode::Sale, 'SALE-POOL');
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 8,
        ]);

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'rental_item_count' => 10,
        ])->assertRedirect()
            ->assertSessionHasErrors('quantity_on_hand');

        $this->assertSame(8, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(0, $variant->items()->count());

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 12,
            'rental_item_count' => 10,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(12, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(0, $variant->items()->count());
    }

    public function test_both_stock_leaves_the_blank_pool_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = $this->variantWithMode(OfferMode::Both, 'BOTH-POOL');
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 15,
        ]);
        foreach (range(1, 6) as $n) {
            InventoryItem::factory()->create([
                'product_variant_id' => $variant->id,
                'asset_code' => 'BOTH-POOL-'.$n,
            ]);
        }

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 9,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(6, $variant->items()->count());

        $this->actingAs($admin)->post(route('admin.inventory.stocks'), [
            'product_variant_id' => $variant->id,
            'rental_item_count' => 4,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $variant->fresh()->stock->quantity_on_hand);
        $this->assertSame(4, $variant->items()->count());
    }

    public function test_get_inventory_stocks_returns_to_the_inventory_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/inventory/stocks')
            ->assertRedirect(route('admin.inventory.index'));
    }

    private function variantWithMode(OfferMode $mode, string $sku): ProductVariant
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
