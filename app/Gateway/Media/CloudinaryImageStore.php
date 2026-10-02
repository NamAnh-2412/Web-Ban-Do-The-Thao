<?php

namespace App\Gateway\Media;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CloudinaryImageStore
{
    public function configured(): bool
    {
        return $this->cloudName() !== ''
            && $this->apiKey() !== ''
            && $this->apiSecret() !== '';
    }

    public function upload(UploadedFile $file): string
    {
        $timestamp = time();
        $folder = (string) config('services.cloudinary.folder', 'webthethao/products');
        $signed = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        try {
            $response = Http::timeout(20)
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($this->endpoint('image/upload'), [
                    'api_key' => $this->apiKey(),
                    'timestamp' => $timestamp,
                    'folder' => $folder,
                    'signature' => $this->sign($signed),
                ])
                ->throw();
        } catch (RequestException $e) {
            report($e);

            throw ValidationException::withMessages([
                'image' => ['Không tải được ảnh lên Cloudinary. Kiểm tra khóa rồi thử lại.'],
            ]);
        }

        $url = $response->json('secure_url');
        if (! is_string($url) || $url === '') {
            throw ValidationException::withMessages([
                'image' => ['Cloudinary không trả về link ảnh.'],
            ]);
        }

        return $url;
    }

    public function delete(?string $imageUrl): void
    {
        $publicId = $this->publicId($imageUrl);
        if ($publicId === null || ! $this->configured()) {
            return;
        }

        $timestamp = time();
        $signed = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        try {
            Http::timeout(15)
                ->asForm()
                ->post($this->endpoint('image/destroy'), [
                    'public_id' => $publicId,
                    'timestamp' => $timestamp,
                    'api_key' => $this->apiKey(),
                    'signature' => $this->sign($signed),
                ])
                ->throw();
        } catch (RequestException $e) {
            report($e);
        }
    }

    public function publicId(?string $imageUrl): ?string
    {
        if (! is_string($imageUrl) || ! str_contains($imageUrl, 'res.cloudinary.com')) {
            return null;
        }

        if (! preg_match('#/image/upload/(?:[^/]+/)*v\d+/(.+)\.[a-zA-Z0-9]+$#', $imageUrl, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param  array<string, int|string>  $params
     */
    private function sign(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '') {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }

        return sha1(implode('&', $pairs).$this->apiSecret());
    }

    private function endpoint(string $action): string
    {
        return 'https://api.cloudinary.com/v1_1/'.$this->cloudName().'/'.$action;
    }

    private function cloudName(): string
    {
        return trim((string) config('services.cloudinary.cloud_name'));
    }

    private function apiKey(): string
    {
        return trim((string) config('services.cloudinary.api_key'));
    }

    private function apiSecret(): string
    {
        return trim((string) config('services.cloudinary.api_secret'));
    }
}
