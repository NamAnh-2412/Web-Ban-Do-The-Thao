<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptLine extends Model
{
    protected $fillable = [
        'stock_receipt_id',
        'product_variant_id',
        'sale_quantity',
        'rental_quantity',
    ];

    protected function casts(): array
    {
        return [
            'stock_receipt_id' => 'integer',
            'product_variant_id' => 'integer',
            'sale_quantity' => 'integer',
            'rental_quantity' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
