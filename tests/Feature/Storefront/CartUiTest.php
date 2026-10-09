<?php

namespace Tests\Feature\Storefront;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use App\Storefront\Support\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_shows_status_badges_thumbnail_and_quantity_controls(): void
    {
        [$saleVariant, $rentVariant] = $this->seedVariants();
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->add([
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 2,
        ]);
        app(CartService::class)->add([
            'line_type' => 'rental',
            'product_variant_id' => $rentVariant->id,
            'quantity' => 1,
            'rental_start' => '2026-10-09',
            'rental_end' => '2026-10-10',
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Trạng thái', false)
            ->assertSee('cart-badge--sale', false)
            ->assertSee('cart-badge--rent', false)
            ->assertSee('cart-qty-btn', false)
            ->assertSee('Chi tiết', false)
            ->assertSee(asset('storage/products/ao.jpg'), false)
            ->assertSee('Tóm tắt đơn hàng', false);
    }

    public function test_customer_can_update_cart_quantity(): void
    {
        [$saleVariant] = $this->seedVariants();
        $user = User::factory()->create();
        $this->actingAs($user);
        app(CartService::class)->add([
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 1,
        ]);
        $lineId = app(CartService::class)->lines()[0]['id'];

        $this->patch(route('cart.quantity', $lineId), ['quantity' => 3])
            ->assertRedirect(route('cart.index'));

        $this->assertSame(3, app(CartService::class)->lines()[0]['quantity']);
    }

    public function test_customer_can_switch_cart_variant(): void
    {
        [$saleVariant] = $this->seedVariants();
        $other = ProductVariant::factory()->create([
            'product_id' => $saleVariant->product_id,
            'sku' => 'AO-WHITE-M',
            'size' => 'M',
            'color' => 'Trắng',
            'sale_price' => 220000,
            'rental_price_per_day' => null,
            'deposit_amount' => null,
            'is_active' => true,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $other->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        app(CartService::class)->add([
            'line_type' => 'sale',
            'product_variant_id' => $saleVariant->id,
            'quantity' => 1,
        ]);
        $lineId = app(CartService::class)->lines()[0]['id'];

        $this->patch(route('cart.variant', $lineId), [
            'product_variant_id' => $other->id,
        ])->assertRedirect(route('cart.index'));

        $this->assertSame($other->id, (int) app(CartService::class)->lines()[0]['product_variant_id']);
    }

    /** @return array{0: ProductVariant, 1: ProductVariant} */
    private function seedVariants(): array
    {
        $saleProduct = Product::factory()->create([
            'name' => 'Áo gym nam',
            'offer_mode' => 'sale',
            'image_url' => 'products/ao.jpg',
            'is_active' => true,
        ]);
        $saleVariant = ProductVariant::factory()->create([
            'product_id' => $saleProduct->id,
            'sku' => 'AO-GYM-M',
            'size' => 'M',
            'color' => 'Xám',
            'sale_price' => 219000,
            'rental_price_per_day' => null,
            'deposit_amount' => null,
            'is_active' => true,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $saleVariant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $rentProduct = Product::factory()->create([
            'name' => 'Giày bóng đá Copa',
            'offer_mode' => 'rental',
            'image_url' => 'products/giay.jpg',
            'is_active' => true,
        ]);
        $rentVariant = ProductVariant::factory()->create([
            'product_id' => $rentProduct->id,
            'sku' => 'GIAY-COPA-42',
            'size' => '42',
            'color' => 'Đen',
            'sale_price' => null,
            'rental_price_per_day' => 50000,
            'deposit_amount' => 200000,
            'is_active' => true,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $rentVariant->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);
        \App\Domain\Inventory\Models\InventoryItem::factory()->create([
            'product_variant_id' => $rentVariant->id,
            'status' => 'available',
        ]);
        \App\Domain\Inventory\Models\InventoryItem::factory()->create([
            'product_variant_id' => $rentVariant->id,
            'status' => 'available',
        ]);

        return [$saleVariant, $rentVariant];
    }
}
