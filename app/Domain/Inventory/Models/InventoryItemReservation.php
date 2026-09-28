<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemReservation extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'order_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ReservationStatus::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
