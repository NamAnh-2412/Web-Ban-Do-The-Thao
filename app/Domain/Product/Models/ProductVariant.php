<?php

namespace App\Domain\Product\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Product\Enums\VariantCondition;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $attributes = [
        'condition' => 'new',
        'is_active' => true,
    ];

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'condition',
        'sale_price',
        'rental_price_per_day',
        'rental_price_per_week',
        'deposit_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'condition' => VariantCondition::class,
            'sale_price' => 'decimal:2',
            'rental_price_per_day' => 'decimal:2',
            'rental_price_per_week' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }
}
