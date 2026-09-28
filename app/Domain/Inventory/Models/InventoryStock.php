<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\ProductVariant;
use Database\Factories\InventoryStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    public function isLow(): bool
    {
        return $this->quantity_on_hand <= $this->low_stock_threshold;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryStockReservation::class);
    }

    protected static function newFactory(): InventoryStockFactory
    {
        return InventoryStockFactory::new();
    }
}
