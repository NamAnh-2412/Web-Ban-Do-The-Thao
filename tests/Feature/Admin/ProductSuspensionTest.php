<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_hides_the_product_without_deactivating_it(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo tạm khóa',
            'slug' => 'ao-tam-khoa',
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'AO-TAM-KHOA',
            'sale_price' => 50000,
            'rental_price_per_day' => null,
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Áo tạm khóa');

        $this->actingAs($staff)
            ->post(route('admin.inventory.suspension', $product))
            ->assertRedirect();

        $product->refresh();
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->is_suspended);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Áo tạm khóa');
        $this->get(route('catalog.show', 'ao-tam-khoa'))->assertNotFound();
        $this->actingAs($staff)
            ->get(route('admin.pos.index'))
            ->assertOk()
            ->assertSee('Áo tạm khóa');

        $this->actingAs($staff)
            ->post(route('admin.inventory.suspension', $product))
            ->assertRedirect();

        $this->assertFalse($product->fresh()->is_suspended);
        $this->get(route('catalog.index'))->assertSee('Áo tạm khóa');
    }

    public function test_dashboard_warns_when_sale_or_rental_stock_is_low(): void
    {
        $staff = User::factory()->staff()->create();
        $sale = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Giày sắp hết',
            'slug' => 'giay-sap-het',
        ]);
        $saleVariant = ProductVariant::factory()->create([
            'product_id' => $sale->id,
            'sku' => 'GIAY-SAP-HET',
            'sale_price' => 100000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $saleVariant->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);

        $rental = Product::factory()->create([
            'offer_mode' => OfferMode::Rental,
            'name' => 'Vợt sắp hết',
            'slug' => 'vot-sap-het',
        ]);
        $rentalVariant = ProductVariant::factory()->create([
            'product_id' => $rental->id,
            'sku' => 'VOT-SAP-HET',
            'sale_price' => null,
            'rental_price_per_day' => 20000,
        ]);
        InventoryItem::factory()->create([
            'product_variant_id' => $rentalVariant->id,
            'asset_code' => 'VOT-SAP-HET-1',
            'status' => ItemStatus::Available,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sắp hết hàng bán')
            ->assertSee('GIAY-SAP-HET')
            ->assertSee('Sắp hết món thuê')
            ->assertSee('VOT-SAP-HET')
            ->assertSee('Tạm khóa');
    }
}
