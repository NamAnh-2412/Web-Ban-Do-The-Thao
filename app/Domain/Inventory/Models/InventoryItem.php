<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Product\Models\ProductVariant;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'available',
    ];

    protected $fillable = [
        'product_variant_id',
        'asset_code',
        'status',
        'condition_note',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'status' => ItemStatus::class,
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryItemReservation::class);
    }

    protected static function newFactory(): InventoryItemFactory
    {
        return InventoryItemFactory::new();
    }
}
