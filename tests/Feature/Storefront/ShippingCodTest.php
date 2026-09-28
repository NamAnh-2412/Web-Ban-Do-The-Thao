<?php

namespace Tests\Feature\Storefront;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\Shipping\Enums\ShippingMethod;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingCodTest extends TestCase
{
    use RefreshDatabase;

    public function test_provinces_and_fee_endpoints_use_ghn_fake(): void
    {
        Http::fake([
            '*/master-data/province' => Http::response([
                'code' => 200,
                'data' => [['ProvinceID' => 202, 'ProvinceName' => 'Hà Nội']],
            ], 200),
            '*/v2/shipping-order/fee' => Http::response([
                'code' => 200,
                'data' => ['total' => 22000],
            ], 200),
        ]);

        $this->getJson(route('shipping.provinces'))
            ->assertOk()
            ->assertJsonPath('data.0.ProvinceName', 'Hà Nội');

        $this->getJson(route('shipping.fee', [
            'to_district_id' => 1442,
            'to_ward_code' => '21211',
        ]))->assertOk()->assertJsonPath('data.total', 22000);
    }

    public function test_cod_delivery_creates_ghn_code_and_keeps_payments_pending(): void
    {
        Http::fake([
            '*/v2/shipping-order/fee' => Http::response([
                'code' => 200,
                'data' => ['total' => 22000],
            ], 200),
            '*/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHN123'],
            ], 200),
        ]);

        $variant = $this->saleVariant();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/gio-hang', [
            'line_type' => 'sale',
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->post('/thanh-toan', [
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0901111222',
            'shipping_address' => '12 Nguyen Hue',
            'shipping_method' => 'delivery',
            'to_province_id' => 202,
            'to_district_id' => 1442,
            'to_ward_code' => '21211',
            'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(ShippingMethod::Delivery, $order->shipping_method);
        $this->assertSame('GHN123', $order->ghn_order_code);
        $this->assertSame(FulfillmentStatus::ReadyToPick, $order->fulfillment_status);
        $this->assertSame(22000.0, (float) $order->shipping_fee);
        $this->assertSame(PaymentMethod::Cod, $order->payments()->first()->method);
        $this->assertSame('pending', $order->payments()->first()->status->value);
        $this->assertGreaterThan((float) $order->merchandise_total, (float) $order->grand_total);
    }

    public function test_fee_rejects_same_pickup_ward(): void
    {
        $this->getJson(route('shipping.fee', [
            'to_district_id' => 1482,
            'to_ward_code' => '1A0101',
        ]))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Địa chỉ nhận trùng phường kho lấy hàng GHN. Chọn quận/phường khác.']);
    }

    private function saleVariant(): ProductVariant
    {
        $product = Product::factory()->create(['offer_mode' => OfferMode::Sale]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sale_price' => 150000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        return $variant;
    }
}
