<?php

namespace Tests\Feature\Storefront;

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

class BestSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_best_sellers_from_paid_sale_lines(): void
    {
        $hot = Product::factory()->create(['name' => 'Giày bán chạy test', 'offer_mode' => OfferMode::Sale]);
        $cold = Product::factory()->create(['name' => 'Áo ít bán test', 'offer_mode' => OfferMode::Sale]);
        $hotVariant = ProductVariant::factory()->create(['product_id' => $hot->id, 'sale_price' => 200000]);
        ProductVariant::factory()->create(['product_id' => $cold->id, 'sale_price' => 100000]);

        $order = Order::factory()->create([
            'user_id' => User::factory(),
            'status' => OrderStatus::Paid,
            'merchandise_total' => 400000,
            'grand_total' => 400000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $hot->id,
            'product_variant_id' => $hotVariant->id,
            'line_type' => LineType::Sale,
            'product_name' => $hot->name,
            'sku' => $hotVariant->sku,
            'quantity' => 5,
            'unit_price' => 200000,
            'line_total' => 1000000,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bán chạy')
            ->assertSee('Giày bán chạy test');
    }
}
