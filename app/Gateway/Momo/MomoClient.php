<?php

namespace App\Gateway\Momo;

use Illuminate\Support\Facades\Http;

class MomoClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $endpoint = (string) config('services.momo.endpoint');
        $response = Http::withOptions([
            'verify' => filter_var(config('services.momo.verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
        ])->post($endpoint, $payload);

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function sign(array $fields): string
    {
        ksort($fields);
        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return hash_hmac('sha256', implode('&', $parts), (string) config('services.momo.secret_key', ''));
    }

    public function createSignature(
        string $amount,
        string $extraData,
        string $ipnUrl,
        string $orderId,
        string $orderInfo,
        string $redirectUrl,
        string $requestId,
        string $requestType,
    ): string {
        $accessKey = (string) config('services.momo.access_key', '');
        $partnerCode = (string) config('services.momo.partner_code', '');
        $rawHash = 'accessKey='.$accessKey
            .'&amount='.$amount
            .'&extraData='.$extraData
            .'&ipnUrl='.$ipnUrl
            .'&orderId='.$orderId
            .'&orderInfo='.$orderInfo
            .'&partnerCode='.$partnerCode
            .'&redirectUrl='.$redirectUrl
            .'&requestId='.$requestId
            .'&requestType='.$requestType;

        return hash_hmac('sha256', $rawHash, (string) config('services.momo.secret_key', ''));
    }

    /** @param  array<string, mixed>  $payload */
    public function isValidResponse(array $payload): bool
    {
        if (! isset($payload['signature'])) {
            return false;
        }

        $accessKey = (string) config('services.momo.access_key', '');
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

        return hash_equals(
            hash_hmac('sha256', $rawHash, (string) config('services.momo.secret_key', '')),
            (string) $payload['signature']
        );
    }

    /** @param  array<string, mixed>  $payload */
    public function isSuccessful(array $payload): bool
    {
        return (string) ($payload['resultCode'] ?? '') === '0';
    }
}
