<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = RentalBooking::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                BookingStatus::Pending,
                BookingStatus::Confirmed,
                BookingStatus::Active,
                BookingStatus::Overdue,
            ])
            ->with(['variant.product', 'extensions', 'incidents'])
            ->orderBy('end_date')
            ->orderBy('id')
            ->get();

        return view('storefront.rentals.schedule', [
            'sessions' => RentalSession::group($bookings),
        ]);
    }
}
