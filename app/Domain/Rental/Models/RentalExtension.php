<?php

namespace App\Domain\Rental\Models;

use App\Domain\Rental\Enums\ExtensionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExtension extends Model
{
    protected $fillable = [
        'rental_booking_id',
        'old_end_date',
        'new_end_date',
        'extra_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'old_end_date' => 'date',
            'new_end_date' => 'date',
            'extra_amount' => 'decimal:2',
            'status' => ExtensionStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RentalBooking::class, 'rental_booking_id');
    }
}
