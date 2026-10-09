<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\LineType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Review\Models\Review;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'line_type',
        'product_id',
        'product_variant_id',
        'product_name',
        'sku',
        'image_url',
        'size',
        'color',
        'unit_price',
        'quantity',
        'rental_start',
        'rental_end',
        'deposit_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'line_type' => LineType::class,
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'rental_start' => 'date',
            'rental_end' => 'date',
            'deposit_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function imageSrc(): ?string
    {
        if (! $this->image_url) {
            return $this->product?->imageSrc();
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

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
