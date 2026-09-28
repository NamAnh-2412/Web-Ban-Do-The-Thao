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

        $this->assertMatchesRegularExpression('/AO-SOLD-SKU[\s\S]{0,800}?>17</', $html);
        $this->assertDoesNotMatchRegularExpression('/AO-SOLD-SKU[\s\S]{0,800}?>26</', $html);
        $this->assertDoesNotMatchRegularExpression('/AO-SOLD-SKU[\s\S]{0,800}?>9</', $html);
    }
}
