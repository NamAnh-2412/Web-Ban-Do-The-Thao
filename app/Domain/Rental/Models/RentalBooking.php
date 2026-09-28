<?php

namespace App\Domain\Rental\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\User\Models\User;
use Database\Factories\RentalBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentalBooking extends Model
{
    /** @use HasFactory<RentalBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'user_id',
        'inventory_item_id',
        'product_variant_id',
        'start_date',
        'end_date',
        'daily_rate',
        'rental_amount',
        'deposit_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'order_item_id' => 'integer',
            'user_id' => 'integer',
            'inventory_item_id' => 'integer',
            'product_variant_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'daily_rate' => 'decimal:2',
            'rental_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'status' => BookingStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class);
    }

    public function rentalReturn(): HasOne
    {
        return $this->hasOne(RentalReturn::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(RentalIncident::class);
    }

    protected static function newFactory(): RentalBookingFactory
    {
        return RentalBookingFactory::new();
    }
}
