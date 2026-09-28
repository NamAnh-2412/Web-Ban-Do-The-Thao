<?php

namespace Tests\Feature\Storefront;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\GatewaySessionStatus;
use App\Domain\Payment\Models\GatewaySession;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_momo_callback_marks_order_paid_without_staff(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => function ($request) {
                $this->assertSame('payWithCC', $request['requestType'] ?? null);

                return Http::response([
                    'resultCode' => 0,
                    'payUrl' => 'https://momo.test/pay',
                    'message' => 'ok',
                ], 200);
            },
        ]);

        [$user, $order] = $this->placeMomoOrder();
        $session = GatewaySession::query()->where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($session);
        $this->assertSame(GatewaySessionStatus::Initiated, $session->status);

        $this->get(route('payments.momo.return', $this->signedPayload($order, $session, 0)))
            ->assertRedirect(route('orders.show', $order->id));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue(
            Payment::query()->where('order_id', $order->id)->get()
                ->every(fn (Payment $row) => $row->status->value === 'completed')
        );
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_invalid_momo_signature_is_rejected(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => Http::response(['payUrl' => 'https://momo.test/pay', 'resultCode' => 0], 200),
        ]);

        [, $order] = $this->placeMomoOrder();
        $session = GatewaySession::query()->where('order_id', $order->id)->first();
        $payload = $this->signedPayload($order, $session, 0);
        $payload['signature'] = 'deadbeef';

        $this->get(route('payments.momo.return', $payload))->assertRedirect();
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_failed_momo_keeps_order_for_retry(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => Http::response(['payUrl' => 'https://momo.test/pay', 'resultCode' => 0], 200),
        ]);

        [$user, $order] = $this->placeMomoOrder();
        $session = GatewaySession::query()->where('order_id', $order->id)->first();

        $this->get(route('payments.momo.return', $this->signedPayload($order, $session, 9000)))
            ->assertRedirect(route('orders.show', $order->id));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(GatewaySessionStatus::Failed, $session->fresh()->status);

        $this->actingAs($user)
            ->post(route('orders.momo', $order->id))
            ->assertRedirect('https://momo.test/pay');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, GatewaySession::query()->where('order_id', $order->id)->count());
    }

    /** @return array{0: User, 1: Order} */
    private function placeMomoOrder(): array
    {
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
            'shipping_address' => '1 Đường Test',
            'shipping_method' => 'pickup',
            'payment_method' => 'momo',
        ])->assertRedirect('https://momo.test/pay');

        return [$user, Order::query()->where('user_id', $user->id)->firstOrFail()];
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

    /** @return array<string, mixed> */
    private function signedPayload(Order $order, GatewaySession $session, int $resultCode): array
    {
        $payload = [
            'amount' => (string) $session->amount,
            'extraData' => (string) $order->id,
            'message' => $resultCode === 0 ? 'Success' : 'Failed',
            'orderId' => $session->gateway_order_id,
            'orderInfo' => 'Thanh toan don WebTheThao #'.$order->id,
            'orderType' => 'momo_wallet',
            'partnerCode' => config('services.momo.partner_code'),
            'payType' => 'napas',
            'requestId' => 'req-1',
            'responseTime' => (string) now()->getTimestampMs(),
            'resultCode' => (string) $resultCode,
            'transId' => '123456',
        ];
        $payload['signature'] = $this->sign($payload);

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
    private function sign(array $payload): string
    {
        $accessKey = (string) config('services.momo.access_key');
        $rawHash = 'accessKey='.$accessKey
            .'&amount='.($payload['amount'] ?? '')
            .'&extraData='.($payload['extraData'] ?? '')
            .'&message='.($payload['message'] ?? '')
            .'&orderId='.($payload['orderId'] ?? '')
            .'&orderInfo='.($payload['orderInfo'] ?? '')
            .'&orderType='.($payload['orderType'] ?? '')
            .'&partnerCode='.($payload['partnerCode'] ?? '')
            .'&payType='.($payload['payType'] ?? '')
            .'&requestId='.($payload['requestId'] ?? '')
            .'&responseTime='.($payload['responseTime'] ?? '')
            .'&resultCode='.($payload['resultCode'] ?? '')
            .'&transId='.($payload['transId'] ?? '');

        return hash_hmac('sha256', $rawHash, (string) config('services.momo.secret_key'));
    }
}
