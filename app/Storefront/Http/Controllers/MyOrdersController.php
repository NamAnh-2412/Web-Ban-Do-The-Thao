<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class MyOrdersController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'thue' ? 'thue' : 'mua';
        $userId = $request->user()->id;

        $orderCount = Order::query()->where('user_id', $userId)->count();

        $orders = $tab === 'mua'
            ? Order::query()
                ->where('user_id', $userId)
                ->with(['items.product'])
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString()
            : new LengthAwarePaginator([], $orderCount, 10);

        $bookings = RentalBooking::query()
            ->where('user_id', $userId)
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

        $sessions = RentalSession::group($bookings);

        return view('storefront.my-orders.index', [
            'tab' => $tab,
            'orders' => $orders,
            'sessions' => $sessions,
            'orderCount' => $orderCount,
            'rentalCount' => count($sessions),
        ]);
    }
}
