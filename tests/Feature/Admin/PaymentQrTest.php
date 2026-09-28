<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentQrSetting;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_qr_and_customer_sees_it_on_pending_order(): void
    {
        Storage::fake('public');

        $staff = User::factory()->staff()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('shop-qr.png', $png);

        $this->actingAs($staff)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('QR chuyển khoản');

        $this->actingAs($staff)
            ->post(route('admin.payments.qr'), [
                'bank_name' => 'ACB',
                'account_name' => 'CUA HANG WTT',
                'account_number' => '9999888877',
                'instructions' => 'Quét QR rồi báo nhân viên.',
                'qr_image' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = PaymentQrSetting::current();
        $this->assertNotNull($row);
        $this->assertSame('ACB', $row->bank_name);
        $this->assertSame('9999888877', $row->account_number);
        Storage::disk('public')->assertExists($row->qr_path);

        $product = Product::factory()->create([
            'offer_mode' => OfferMode::Sale,
            'name' => 'Áo QR',
            'slug' => 'ao-qr-'.uniqid(),
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'QR-SALE-'.uniqid(),
            'sale_price' => 219000,
            'rental_price_per_day' => null,
        ]);
        InventoryStock::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

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
            'shipping_address' => '1 Đường Test, Q.1',
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        $this->get(route('orders.show', $order->id))
            ->assertOk()
            ->assertSee('Quét QR MoMo / ngân hàng')
            ->assertSee('ACB')
            ->assertSee('9999888877')
            ->assertSee('WTT'.$order->id)
            ->assertSee('219.000')
            ->assertSee('storage/'.$row->qr_path)
            ->assertSee('Chờ nhân viên xác nhận đã nhận tiền')
            ->assertDontSee('Tôi đã thanh toán');
    }
}
