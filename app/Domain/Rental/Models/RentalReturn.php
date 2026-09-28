<?php

namespace App\Domain\Rental\Models;

use App\Domain\Rental\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalReturn extends Model
{
    protected $fillable = [
        'rental_booking_id',
        'returned_at',
        'staff_user_id',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'staff_user_id' => 'integer',
            'condition' => ReturnCondition::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RentalBooking::class, 'rental_booking_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(RentalIncident::class);
    }
}
