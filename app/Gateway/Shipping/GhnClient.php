<?php

namespace App\Gateway\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhnClient
{
    /** @param  array<string, mixed>  $query */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('get', $uri, $query);
    }

    /** @param  array<string, mixed>  $payload */
    public function post(string $uri, array $payload = []): array
    {
        return $this->request('post', $uri, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $data): array
    {
        try {
            $pending = Http::baseUrl((string) config('services.ghn.base_url'))
                ->withOptions([
                    'verify' => filter_var(config('services.ghn.verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
                ])
                ->acceptJson()
                ->timeout(15)
                ->withHeaders([
                    'Token' => (string) config('services.ghn.token', ''),
                    'ShopId' => (string) config('services.ghn.shop_id', ''),
                    'Content-Type' => 'application/json',
                ]);

            $response = $method === 'get'
                ? $pending->get($uri, $data)
                : $pending->post($uri, $data);

            $body = $response->json();

            if (! $response->successful()) {
                Log::warning('GHN request failed', [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return is_array($body)
                    ? $body
                    : ['code' => $response->status(), 'message' => 'GHN API request failed.'];
            }

            return is_array($body) ? $body : ['code' => -1, 'message' => 'GHN returned an empty response.'];
        } catch (ConnectionException $exception) {
            Log::error('Unable to connect to GHN', ['uri' => $uri, 'error' => $exception->getMessage()]);

            return ['code' => -1, 'message' => 'Unable to connect to GHN.'];
        }
    }
}
