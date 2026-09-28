<?php

namespace App\Gateway\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\GatewaySessionStatus;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\GatewaySession;
use App\Gateway\Momo\MomoClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MomoCheckoutService
{
    public function __construct(
        private MomoClient $momo,
        private PaymentOrchestrator $payments,
        private ShippingService $shipping,
    ) {}

    public function payUrl(Order $order, bool $forceNew = false): string
    {
        if (! $order->canRetryGateway()) {
            throw ValidationException::withMessages([
                'order' => ['Đơn này không thể thanh toán MoMo.'],
            ]);
        }

        $session = $this->sessionFor($order, $forceNew);
        $amount = (string) $order->payableNow();
        $extraData = (string) $order->id;
        $orderId = $order->id.'_'.$session->id.'_'.time();
        $requestId = (string) Str::uuid();
        $orderInfo = 'Thanh toan don WebTheThao #'.$order->id;
        $requestType = 'payWithCC';
        $redirectUrl = (string) (config('services.momo.redirect_url') ?: route('payments.momo.return'));
        $ipnUrl = (string) (config('services.momo.ipn_url') ?: route('payments.momo.ipn'));
        $partnerCode = (string) config('services.momo.partner_code', '');

        $payload = [
            'partnerCode' => $partnerCode,
            'partnerName' => 'WebTheThao',
            'storeId' => 'WebTheThao',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $this->momo->createSignature(
                $amount,
                $extraData,
                $ipnUrl,
                $orderId,
                $orderInfo,
                $redirectUrl,
                $requestId,
                $requestType,
            ),
        ];

        $session->gateway_order_id = $orderId;
        $session->amount = (int) $amount;
        $session->request_payload = $payload;
        $session->status = GatewaySessionStatus::Initiated;
        $session->save();

        $result = $this->momo->create($payload);
        $session->response_payload = $result;
        $session->result_code = isset($result['resultCode']) ? (int) $result['resultCode'] : null;
        $session->message = $result['message'] ?? null;

        if (! isset($result['payUrl'])) {
            $session->status = GatewaySessionStatus::Failed;
            $session->save();

            throw ValidationException::withMessages([
                'payment_method' => [$result['message'] ?? 'Không kết nối được MoMo.'],
            ]);
        }

        $session->save();

        return (string) $result['payUrl'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): string
    {
        if (! $this->momo->isValidResponse($payload)) {
            return 'invalid';
        }

        if (! $this->momo->isSuccessful($payload)) {
            $this->markFailed($payload);

            return 'failed';
        }

        return $this->complete($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function complete(array $payload): string
    {
        return DB::transaction(function () use ($payload) {
            $session = GatewaySession::query()
                ->where('gateway', 'momo')
                ->where('gateway_order_id', $payload['orderId'] ?? '')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return 'invalid';
            }

            $order = Order::query()->lockForUpdate()->find($session->order_id);
            if ($order === null) {
                return 'invalid';
            }

            if ((int) $session->amount !== (int) ($payload['amount'] ?? 0)) {
                $this->failSession($session, $payload);

                return 'invalid';
            }

            if ($session->status === GatewaySessionStatus::Paid
                || in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Completed], true)
            ) {
                return 'already_paid';
            }

            $txnId = isset($payload['transId']) ? (string) $payload['transId'] : null;
            $this->payments->captureGateway($order, $txnId, 'MoMo tự chốt. Một lần thu, phân bổ tiền hàng và cọc.');

            $session->status = GatewaySessionStatus::Paid;
            $session->provider_txn_id = $txnId;
            $session->result_code = (int) ($payload['resultCode'] ?? 0);
            $session->message = $payload['message'] ?? null;
            $session->response_payload = $payload;
            $session->paid_at = now();
            $session->save();

            $order = $order->fresh();
            if ($order !== null && $order->isDelivery()) {
                $this->shipping->createShipment($order, true);
            }

            return 'paid';
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(array $payload): void
    {
        $session = GatewaySession::query()
            ->where('gateway', 'momo')
            ->where('gateway_order_id', $payload['orderId'] ?? '')
            ->first();

        if ($session === null || $session->status === GatewaySessionStatus::Paid) {
            return;
        }

        $this->failSession($session, $payload);
    }

    /** @param  array<string, mixed>  $payload */
    private function failSession(GatewaySession $session, array $payload): void
    {
        $session->status = GatewaySessionStatus::Failed;
        $session->result_code = isset($payload['resultCode']) ? (int) $payload['resultCode'] : null;
        $session->message = $payload['message'] ?? null;
        $session->response_payload = $payload;
        $session->save();
    }

    private function sessionFor(Order $order, bool $forceNew): GatewaySession
    {
        if (! $forceNew) {
            $reusable = GatewaySession::query()
                ->where('order_id', $order->id)
                ->where('gateway', 'momo')
                ->where('status', GatewaySessionStatus::Pending)
                ->whereNull('gateway_order_id')
                ->latest('id')
                ->first();

            if ($reusable) {
                return $reusable;
            }
        }

        return GatewaySession::query()->create([
            'order_id' => $order->id,
            'gateway' => 'momo',
            'amount' => $order->payableNow(),
            'status' => GatewaySessionStatus::Pending,
        ]);
    }
}
