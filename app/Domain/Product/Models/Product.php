<?php

namespace App\Domain\Product\Models;

use App\Domain\Product\Enums\OfferMode;
use App\Domain\Review\Models\Review;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sport_id',
        'name',
        'slug',
        'description',
        'offer_mode',
        'image_url',
        'video_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'offer_mode' => OfferMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isSaleSoldOut(): bool
    {
        if (! in_array($this->offer_mode, [OfferMode::Sale, OfferMode::Both], true)) {
            return false;
        }

        $saleVariants = $this->variants->filter(fn (ProductVariant $variant) => $variant->sale_price !== null);
        if ($saleVariants->isEmpty()) {
            return false;
        }

        return $saleVariants->every(function (ProductVariant $variant) {
            $stock = $variant->stock;
            if ($stock === null) {
                return true;
            }

            return ((int) $stock->quantity_on_hand - (int) $stock->quantity_reserved) <= 0;
        });
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

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
