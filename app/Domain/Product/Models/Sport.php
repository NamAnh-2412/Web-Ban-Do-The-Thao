<?php

namespace App\Domain\Product\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    /** @use HasFactory<SportFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image_url',
        'headline',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imageSrc(): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        if (str_starts_with($this->image_url, 'http://') || str_starts_with($this->image_url, 'https://')) {
            return $this->image_url;
        }

        if (str_starts_with($this->image_url, '/media/')) {
            $relative = ltrim($this->image_url, '/');
            if (! is_file(public_path($relative))) {
                return null;
            }

            return asset($relative);
        }

        if (str_starts_with($this->image_url, '/')) {
            return asset(ltrim($this->image_url, '/'));
        }

        return asset('storage/'.$this->image_url);
    }

    public function displayHeadline(): string
    {
        $headline = trim((string) $this->headline);
        if ($headline !== '') {
            return $headline;
        }

        return 'Bộ sưu tập '.$this->name;
    }

    public function displayDescription(): string
    {
        $description = trim((string) $this->description);
        if ($description !== '') {
            return $description;
        }

        return 'Khám phá trang phục và dụng cụ chính hãng';
    }

    protected static function newFactory(): SportFactory
    {
        return SportFactory::new();
    }
}
