<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Services\OrderWriter;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentWriter;
use App\Domain\Rental\Enums\BookingStatus;
use App\Domain\Rental\Enums\ExtensionStatus;
use App\Domain\Rental\Enums\ReturnCondition;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Models\RentalExtension;
use App\Domain\Rental\Services\RentalSession;
use App\Domain\Rental\Services\RentalWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function __construct(
        private RentalWriter $writer,
        private OrderWriter $orders,
        private PaymentWriter $payments,
    ) {}

    public function index(Request $request): View
    {
        $this->writer->markOverdue();

        $status = BookingStatus::tryFrom((string) $request->string('status'));
        $bookings = RentalBooking::query()
            ->with(['user', 'variant.product'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        $sessions = RentalSession::group($bookings);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;
        $paged = new LengthAwarePaginator(
            $sessions->forPage($page, $perPage)->values(),
            $sessions->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.rentals.index', [
            'sessions' => $paged,
            'status' => $status,
        ]);
    }

    public function show(RentalBooking $booking): View
    {
        $bookings = RentalSession::siblings($booking);
        $lead = $bookings->first() ?? $booking->load(['user', 'variant.product', 'extensions', 'rentalReturn', 'incidents', 'item', 'order']);

        $outcomes = $bookings->mapWithKeys(fn (RentalBooking $row) => [
            $row->id => $this->writer->depositOutcome($row),
        ]);
        $refunded = $this->refundedFlags($bookings);

        return view('admin.rentals.show', [
            'booking' => $lead,
            'bookings' => $bookings,
            'sessionStatus' => RentalSession::status($bookings),
            'outcomes' => $outcomes,
            'refunded' => $refunded,
            'depositSuggested' => $bookings->sum(function (RentalBooking $row) use ($outcomes, $refunded) {
                if ($row->status !== BookingStatus::Returned || ($refunded[$row->id] ?? false)) {
                    return 0;
                }

                return (float) ($outcomes[$row->id]['refund_suggested'] ?? 0);
            }),
        ]);
    }

    public function confirm(RentalBooking $booking): RedirectResponse
    {
        foreach (RentalSession::siblings($booking) as $row) {
            if ($row->status === BookingStatus::Pending) {
                $this->writer->confirm($row);
            }
        }

        return back()->with('success', 'Đã xác nhận buổi thuê.');
    }

    public function activate(RentalBooking $booking): RedirectResponse
    {
        $handed = 0;
        foreach (RentalSession::siblings($booking) as $row) {
            if ($row->status === BookingStatus::Confirmed) {
                $this->writer->activate($row);
                $handed++;
            }
        }
        $order = $booking->order ?? RentalSession::siblings($booking)->first()?->order;
        if ($order) {
            $this->orders->markProcessing($order);
        }

        return back()->with('success', $handed > 1
            ? "Đã giao {$handed} món trong buổi thuê."
            : 'Đã giao đồ (đang thuê).');
    }

    public function requestExtension(Request $request, RentalBooking $booking): RedirectResponse
    {
        $siblings = RentalSession::siblings($booking);
        $lead = $siblings->first() ?? $booking;
        $data = $request->validate([
            'new_end_date' => ['required', 'date', 'after:'.$lead->end_date->toDateString()],
        ]);
        $this->writer->requestExtensionForSession($booking, $data['new_end_date']);

        return back()->with('success', 'Đã tạo yêu cầu gia hạn cho buổi thuê.');
    }

    public function collectAndApproveExtension(RentalBooking $booking, RentalExtension $extension): RedirectResponse
    {
        $this->assertExtension($booking, $extension);
        $order = $booking->order;
        if ($order === null) {
            throw ValidationException::withMessages(['status' => ['Lịch thuê không gắn đơn.']]);
        }

        $amount = (float) $extension->extra_amount;
        if ($amount > 0) {
            $this->payments->recordCollected(
                $order->id,
                (int) $order->user_id,
                $amount,
                'Thu thêm gia hạn lịch #'.$booking->id,
            );
            $this->orders->addCollectedRental($order, $amount);
        }
        $this->writer->approveExtension($extension);

        return back()->with('success', 'Đã thu thêm và duyệt gia hạn.');
    }

    public function approveExtension(RentalBooking $booking, RentalExtension $extension): RedirectResponse
    {
        $this->assertExtension($booking, $extension);
        $this->writer->approveExtension($extension);

        return back()->with('success', 'Đã duyệt gia hạn.');
    }

    public function rejectExtension(RentalBooking $booking, RentalExtension $extension): RedirectResponse
    {
        $this->assertExtension($booking, $extension);
        $this->writer->rejectExtension($extension);

        return back()->with('success', 'Đã từ chối gia hạn.');
    }

    public function refundDeposit(RentalBooking $booking): RedirectResponse
    {
        $refundedCount = 0;
        $refundedAmount = 0.0;

        foreach (RentalSession::siblings($booking) as $row) {
            if ($row->status !== BookingStatus::Returned || $this->depositAlreadyRefunded($row)) {
                continue;
            }
            $order = $row->order;
            if ($order === null) {
                continue;
            }
            $amount = (float) $this->writer->depositOutcome($row->fresh(['incidents']))['refund_suggested'];
            if ($amount <= 0) {
                continue;
            }
            $remaining = $this->payments->refundableRemaining($order->id);
            if ($amount - 0.001 > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => ['Không hoàn vượt số đã thu còn lại.'],
                ]);
            }
            $this->payments->refund(
                $order->id,
                (int) $order->user_id,
                $amount,
                'Hoàn cọc lịch thuê #'.$row->id,
            );
            $refundedCount++;
            $refundedAmount += $amount;
        }

        if ($refundedCount === 0) {
            throw ValidationException::withMessages([
                'amount' => ['Không còn cọc để hoàn cho buổi này.'],
            ]);
        }

        return back()->with('success', 'Đã hoàn cọc '.number_format($refundedAmount, 0, ',', '.').'đ.');
    }

    public function returnBooking(Request $request, RentalBooking $booking): RedirectResponse
    {
        $siblings = RentalSession::siblings($booking);
        $open = $siblings->filter(fn (RentalBooking $row) => in_array($row->status, [
            BookingStatus::Active,
            BookingStatus::Overdue,
        ], true));

        if ($request->has('items')) {
            $data = $request->validate([
                'items' => ['required', 'array'],
                'items.*.condition' => ['required', 'in:good,damaged,lost'],
                'items.*.notes' => ['nullable', 'string'],
                'items.*.incident_description' => ['nullable', 'string'],
                'items.*.incident_fee' => ['nullable', 'numeric', 'min:0'],
            ]);
            foreach ($open as $row) {
                $payload = $data['items'][(string) $row->id] ?? $data['items'][$row->id] ?? null;
                if (! is_array($payload)) {
                    throw ValidationException::withMessages([
                        'items' => ['Chọn tình trạng cho từng món trong buổi.'],
                    ]);
                }
                $this->writer->returnBooking(
                    $row,
                    (int) $request->user()->id,
                    ReturnCondition::from($payload['condition']),
                    $payload['notes'] ?? null,
                    $payload['incident_description'] ?? null,
                    isset($payload['incident_fee']) ? (float) $payload['incident_fee'] : null,
                );
            }
        } else {
            $data = $request->validate([
                'condition' => ['required', 'in:good,damaged,lost'],
                'notes' => ['nullable', 'string'],
                'incident_description' => ['nullable', 'string'],
                'incident_fee' => ['nullable', 'numeric', 'min:0'],
            ]);
            $this->writer->returnBooking(
                $booking,
                (int) $request->user()->id,
                ReturnCondition::from($data['condition']),
                $data['notes'] ?? null,
                $data['incident_description'] ?? null,
                isset($data['incident_fee']) ? (float) $data['incident_fee'] : null,
            );
        }

        $order = $booking->fresh()->order ?? $siblings->first()?->order;
        if ($order) {
            $this->orders->completeIfRentalsReturned($order->fresh(['bookings']));
        }

        return back()->with('success', 'Đã xác nhận trả đồ.');
    }

    private function assertExtension(RentalBooking $booking, RentalExtension $extension): void
    {
        if ((int) $extension->rental_booking_id !== (int) $booking->id) {
            abort(404);
        }
    }

    /** @param  Collection<int, RentalBooking>  $bookings */
    private function refundedFlags(Collection $bookings): Collection
    {
        $orderIds = $bookings->pluck('order_id')->filter()->unique()->values();
        if ($orderIds->isEmpty()) {
            return $bookings->mapWithKeys(fn (RentalBooking $row) => [$row->id => false]);
        }

        $notes = Payment::query()
            ->whereIn('order_id', $orderIds)
            ->where('kind', PaymentKind::Refund)
            ->where('status', PaymentStatus::Completed)
            ->pluck('note');

        return $bookings->mapWithKeys(function (RentalBooking $row) use ($notes) {
            if ($row->order_id === null) {
                return [$row->id => false];
            }

            $needle = 'Hoàn cọc lịch thuê #'.$row->id;

            return [$row->id => $notes->contains(fn ($note) => str_contains((string) $note, $needle))];
        });
    }

    private function depositAlreadyRefunded(RentalBooking $booking): bool
    {
        return (bool) $this->refundedFlags(collect([$booking]))->get($booking->id, false);
    }
}
