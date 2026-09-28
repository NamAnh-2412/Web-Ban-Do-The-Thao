<?php

namespace App\Gateway\Services;

use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\Shipping\Enums\ShippingMethod;
use App\Gateway\Shipping\GhnClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function __construct(private GhnClient $ghn) {}

    /** @return list<array<string, mixed>> */
    public function provinces(): array
    {
        return $this->data($this->ghn->get('/master-data/province'));
    }

    /** @return list<array<string, mixed>> */
    public function districts(int $provinceId): array
    {
        return $this->data($this->ghn->get('/master-data/district', [
            'province_id' => $provinceId,
        ]));
    }

    /** @return list<array<string, mixed>> */
    public function wards(int $districtId): array
    {
        return $this->data($this->ghn->get('/master-data/ward', [
            'district_id' => $districtId,
        ]));
    }

    public function quoteFee(int $toDistrictId, string $toWardCode, int $itemCount = 1): int
    {
        $this->assertDeliverableWard($toDistrictId, $toWardCode);

        $weight = max(1, $itemCount) * (int) config('services.ghn.default_weight', 200);
        $response = $this->ghn->post('/v2/shipping-order/fee', array_merge($this->package($weight), [
            'shop_id' => (int) config('services.ghn.shop_id', 0),
            'from_district_id' => (int) config('services.ghn.from_district_id', 0),
            'to_district_id' => $toDistrictId,
            'to_ward_code' => $toWardCode,
        ]));

        if ((int) ($response['code'] ?? 0) !== 200) {
            throw ValidationException::withMessages([
                'to_district_id' => [$response['message'] ?? 'Không tính được phí GHN.'],
            ]);
        }

        return (int) ($response['data']['total'] ?? 0);
    }

    public function createShipment(Order $order, bool $alreadyPaid): void
    {
        if ($order->shipping_method !== ShippingMethod::Delivery) {
            return;
        }

        if ($order->ghn_order_code) {
            return;
        }

        $order->loadMissing('items');
        $weight = 0;
        $items = [];
        foreach ($order->items as $item) {
            $itemWeight = (int) config('services.ghn.default_weight', 200);
            $weight += $itemWeight * (int) $item->quantity;
            $items[] = [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'price' => (int) round((float) $item->line_total),
                'weight' => $itemWeight,
            ];
        }

        $codAmount = $alreadyPaid ? 0 : $order->payableNow();
        $this->assertDeliverableWard((int) $order->to_district_id, (string) $order->to_ward_code);
        $pickup = $this->pickupAddress();

        $response = $this->ghn->post('/v2/shipping-order/create', array_merge($this->package(max($weight, 200)), [
            'shop_id' => (int) config('services.ghn.shop_id', 0),
            'payment_type_id' => 2,
            'note' => 'Đơn WebTheThao #'.$order->id,
            'required_note' => 'KHONGCHOXEMHANG',
            ...$pickup,
            'to_name' => (string) $order->shipping_name,
            'to_phone' => (string) $order->shipping_phone,
            'to_address' => (string) $order->shipping_address,
            'to_ward_code' => (string) $order->to_ward_code,
            'to_district_id' => (int) $order->to_district_id,
            'cod_amount' => $codAmount,
            'items' => $items,
        ]));

        if ((int) ($response['code'] ?? 0) === 200 && isset($response['data']['order_code'])) {
            $order->ghn_order_code = (string) $response['data']['order_code'];
            $order->fulfillment_status = FulfillmentStatus::ReadyToPick;
            $order->save();

            return;
        }

        Log::error('GHN create order failed', [
            'order_id' => $order->id,
            'response' => $response,
        ]);

        $order->fulfillment_status = FulfillmentStatus::Failed;
        $order->save();
    }

    public function cancelShipment(Order $order): void
    {
        if (! $order->ghn_order_code) {
            return;
        }

        if ($order->fulfillment_status === null || ! $order->fulfillment_status->canCancelOnGhn()) {
            return;
        }

        $this->ghn->post('/v2/switch-status/cancel', [
            'shop_id' => (int) config('services.ghn.shop_id', 0),
            'order_codes' => [$order->ghn_order_code],
        ]);

        $order->fulfillment_status = FulfillmentStatus::Cancelled;
        $order->save();
    }

    private function assertDeliverableWard(int $toDistrictId, string $toWardCode): void
    {
        $fromDistrictId = (int) config('services.ghn.from_district_id', 0);
        $fromWardCode = (string) config('services.ghn.from_ward_code');

        if (
            $fromDistrictId > 0
            && $fromWardCode !== ''
            && $toDistrictId === $fromDistrictId
            && $toWardCode === $fromWardCode
        ) {
            throw ValidationException::withMessages([
                'to_ward_code' => ['Địa chỉ nhận trùng phường kho lấy hàng GHN. Chọn quận/phường khác.'],
            ]);
        }
    }

    /** @return array<string, int|string> */
    private function pickupAddress(): array
    {
        $fromDistrictId = (int) config('services.ghn.from_district_id', 0);
        $fromWardCode = (string) config('services.ghn.from_ward_code');
        $fromName = (string) config('services.ghn.from_name');
        $fromPhone = (string) config('services.ghn.from_phone');
        $fromAddress = (string) config('services.ghn.from_address');

        return [
            'from_name' => $fromName,
            'from_phone' => $fromPhone,
            'from_address' => $fromAddress,
            'from_ward_code' => $fromWardCode,
            'from_district_id' => $fromDistrictId,
            'return_name' => $fromName,
            'return_phone' => $fromPhone,
            'return_address' => $fromAddress,
            'return_ward_code' => $fromWardCode,
            'return_district_id' => $fromDistrictId,
        ];
    }

    /** @return array<string, int> */
    private function package(int $weight): array
    {
        return [
            'weight' => max($weight, (int) config('services.ghn.default_weight', 200)),
            'length' => 15,
            'width' => 15,
            'height' => 10,
            'service_type_id' => 2,
            'insurance_value' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function data(array $response): array
    {
        $data = $response['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }
}
