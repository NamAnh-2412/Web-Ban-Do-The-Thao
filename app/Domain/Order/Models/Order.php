<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Domain\Shipping\Enums\ShippingMethod;
use App\Domain\User\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'channel',
        'shipping_method',
        'status',
        'merchandise_total',
        'rental_total',
        'deposit_total',
        'discount_total',
        'shipping_fee',
        'grand_total',
        'coupon_code',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'to_province_id',
        'to_district_id',
        'to_ward_code',
        'ghn_order_code',
        'fulfillment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'channel' => OrderChannel::class,
            'shipping_method' => ShippingMethod::class,
            'status' => OrderStatus::class,
            'merchandise_total' => 'decimal:2',
            'rental_total' => 'decimal:2',
            'deposit_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'to_province_id' => 'integer',
            'to_district_id' => 'integer',
            'fulfillment_status' => FulfillmentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RentalBooking::class);
    }

    public function customerKindLabel(): string
    {
        $this->loadMissing('items');

        $hasSale = $this->items->contains(fn (OrderItem $item) => $item->line_type === LineType::Sale);
        $hasRent = $this->items->contains(fn (OrderItem $item) => $item->line_type === LineType::Rental);

        if ($hasSale && $hasRent) {
            return 'Hỗn hợp';
        }

        return $hasRent ? 'Thuê' : 'Mua';
    }

    public function isSaleOnly(): bool
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (OrderItem $item) => $item->line_type === LineType::Sale);
    }

    public function hasBlockingRental(): bool
    {
        $this->loadMissing('bookings');

        return $this->bookings->contains(fn (RentalBooking $booking) => in_array($booking->status, [
            BookingStatus::Active,
            BookingStatus::Overdue,
            BookingStatus::Returned,
        ], true));
    }

    /** Tiền bán + thuê − giảm; không gồm cọc và ship. */
    public function revenueAmount(): float
    {
        return round((float) $this->merchandise_total + (float) $this->rental_total - (float) $this->discount_total, 2);
    }

    public function isDelivery(): bool
    {
        return $this->shipping_method === ShippingMethod::Delivery;
    }

    public function payableNow(): int
    {
        return (int) round((float) $this->grand_total);
    }

    public function canRetryGateway(): bool
    {
        if ($this->channel !== OrderChannel::Online) {
            return false;
        }

        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Confirmed], true);
    }

    public function canBeCancelled(): bool
    {
        if ($this->status->canCancel()) {
            return true;
        }

        if (! $this->status->canCancelAfterPayment()) {
            return false;
        }

        if ($this->isSaleOnly()) {
            return true;
        }

        return ! $this->hasBlockingRental();
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
