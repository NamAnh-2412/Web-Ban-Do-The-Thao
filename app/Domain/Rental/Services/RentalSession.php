<?php

namespace App\Domain\Rental\Services;

use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use Illuminate\Support\Collection;

class RentalSession
{
    public static function key(RentalBooking $booking): string
    {
        $group = $booking->order_id !== null ? 'o'.$booking->order_id : 'u'.$booking->user_id;

        return $group.'|'.$booking->start_date->toDateString().'|'.$booking->end_date->toDateString();
    }

    /** @return Collection<int, RentalBooking> */
    public static function siblings(RentalBooking $booking): Collection
    {
        return RentalBooking::query()
            ->with(['user', 'variant.product', 'item', 'extensions', 'rentalReturn', 'incidents', 'order'])
            ->where('user_id', $booking->user_id)
            ->when(
                $booking->order_id !== null,
                fn ($query) => $query->where('order_id', $booking->order_id),
                fn ($query) => $query->whereNull('order_id'),
            )
            ->whereDate('start_date', $booking->start_date->toDateString())
            ->whereDate('end_date', $booking->end_date->toDateString())
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, RentalBooking>  $bookings
     * @return Collection<int, array{lead: RentalBooking, bookings: Collection<int, RentalBooking>, status: BookingStatus, item_count: int}>
     */
    public static function group(Collection $bookings): Collection
    {
        return $bookings
            ->groupBy(fn (RentalBooking $booking) => self::key($booking))
            ->map(function (Collection $items) {
                $sorted = $items->sortBy('id')->values();

                return [
                    'lead' => $sorted->first(),
                    'bookings' => $sorted,
                    'status' => self::status($sorted),
                    'item_count' => $sorted->count(),
                ];
            })
            ->sortByDesc(fn (array $session) => $session['lead']->id)
            ->values();
    }

    /** @param  Collection<int, RentalBooking>  $bookings */
    public static function status(Collection $bookings): BookingStatus
    {
        if ($bookings->contains(fn (RentalBooking $booking) => $booking->status === BookingStatus::Overdue)) {
            return BookingStatus::Overdue;
        }
        if ($bookings->contains(fn (RentalBooking $booking) => $booking->status === BookingStatus::Active)) {
            return BookingStatus::Active;
        }
        if ($bookings->contains(fn (RentalBooking $booking) => $booking->status === BookingStatus::Confirmed)) {
            return BookingStatus::Confirmed;
        }
        if ($bookings->contains(fn (RentalBooking $booking) => $booking->status === BookingStatus::Pending)) {
            return BookingStatus::Pending;
        }
        if ($bookings->every(fn (RentalBooking $booking) => $booking->status === BookingStatus::Returned)) {
            return BookingStatus::Returned;
        }

        return BookingStatus::Cancelled;
    }
}
