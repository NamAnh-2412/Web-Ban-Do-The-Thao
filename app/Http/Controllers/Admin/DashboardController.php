<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Order\Enums\LineType;
use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalSession;
use App\Domain\Rental\Services\RentalWriter;
use App\Domain\Report\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private RentalWriter $rentals,
        private ReportService $reports,
    ) {}

    public function __invoke(): View
    {
        $this->rentals->markOverdue();

        $todayOrders = $this->todayOrders();
        $weekRevenue = $this->reports->weekRevenue();

        return view('admin.dashboard', [
            'todayOrderCount' => (clone $todayOrders)->count(),
            'todaySaleOrders' => (clone $todayOrders)
                ->whereHas('items', fn (Builder $query) => $query->where('line_type', LineType::Sale))
                ->whereDoesntHave('items', fn (Builder $query) => $query->where('line_type', LineType::Rental))
                ->count(),
            'todayRentalOrders' => (clone $todayOrders)
                ->whereHas('items', fn (Builder $query) => $query->where('line_type', LineType::Rental))
                ->whereDoesntHave('items', fn (Builder $query) => $query->where('line_type', LineType::Sale))
                ->count(),
            'todayMixedOrders' => (clone $todayOrders)
                ->whereHas('items', fn (Builder $query) => $query->where('line_type', LineType::Sale))
                ->whereHas('items', fn (Builder $query) => $query->where('line_type', LineType::Rental))
                ->count(),
            'revenueLabels' => $weekRevenue['labels'],
            'revenueValues' => $weekRevenue['values'],
            'onlinePendingOrders' => $this->onlinePending()->count(),
            'handoverBookings' => RentalSession::group(
                RentalBooking::query()->where('status', BookingStatus::Confirmed)->get()
            )->count(),
            'overdueBookings' => RentalSession::group(
                RentalBooking::query()->where('status', BookingStatus::Overdue)->get()
            )->count(),
            'activeBookings' => RentalSession::group(
                RentalBooking::query()->where('status', BookingStatus::Active)->get()
            )->count(),
            'lowSaleCount' => $this->lowSaleStocks()->count(),
            'emptyRentalCount' => $this->emptyRentalVariants()->count(),
            'onlinePendingRows' => $this->onlinePending()
                ->with('user')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'actionSessionRows' => RentalSession::group(
                RentalBooking::query()
                    ->with(['user', 'variant.product'])
                    ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Overdue])
                    ->orderByRaw("CASE status WHEN 'overdue' THEN 0 ELSE 1 END")
                    ->orderBy('end_date')
                    ->get()
            )->take(5),
            'lowSaleRows' => $this->lowSaleStocks()
                ->with('variant.product')
                ->orderBy('quantity_on_hand')
                ->limit(5)
                ->get(),
            'emptyRentalRows' => $this->emptyRentalVariants()
                ->with('product')
                ->orderBy('sku')
                ->limit(5)
                ->get(),
        ]);
    }

    private function todayOrders(): Builder
    {
        return Order::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('status', '!=', OrderStatus::Cancelled);
    }

    private function onlinePending(): Builder
    {
        return Order::query()
            ->where('channel', OrderChannel::Online)
            ->where('status', OrderStatus::Pending);
    }

    private function lowSaleStocks(): Builder
    {
        return InventoryStock::query()
            ->whereColumn('quantity_on_hand', '<=', 'low_stock_threshold')
            ->whereHas('variant.product', fn (Builder $query) => $query->whereIn('offer_mode', OfferMode::matching('sale')));
    }

    private function emptyRentalVariants(): Builder
    {
        return ProductVariant::query()
            ->whereHas('product', fn (Builder $query) => $query->whereIn('offer_mode', OfferMode::matching('rental')))
            ->whereDoesntHave('items', fn (Builder $query) => $query->where('status', ItemStatus::Available));
    }
}
