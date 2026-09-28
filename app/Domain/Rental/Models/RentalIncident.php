<?php

namespace App\Domain\Rental\Models;

use App\Domain\Rental\Enums\IncidentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalIncident extends Model
{
    protected $fillable = [
        'rental_booking_id',
        'rental_return_id',
        'type',
        'description',
        'fee_amount',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'type' => IncidentType::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RentalBooking::class, 'rental_booking_id');
    }

    public function rentalReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class, 'rental_return_id');
    }
}
