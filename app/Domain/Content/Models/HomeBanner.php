<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    protected $fillable = [
        'image_url',
    ];

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public static function displayUrl(): string
    {
        $row = static::current();
        if ($row === null || $row->image_url === '') {
            return asset('media/images/home-banner.png');
        }

        return $row->resolvedUrl();
    }

    public function resolvedUrl(): string
    {
        $url = (string) $this->image_url;

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return asset(ltrim($url, '/'));
        }

        return asset('storage/'.$url);
    }
}
