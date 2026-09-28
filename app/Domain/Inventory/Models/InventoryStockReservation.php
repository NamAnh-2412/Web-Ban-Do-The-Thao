<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockReservation extends Model
{
    protected $fillable = [
        'inventory_stock_id',
        'order_id',
        'quantity',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
