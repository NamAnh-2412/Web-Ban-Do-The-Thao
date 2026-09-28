<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Enums\OrderChannel;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderWriter;
use App\Domain\Payment\Services\PaymentWriter;
use App\Domain\Shipping\Enums\FulfillmentStatus;
use App\Gateway\Services\CheckoutOrchestrator;
use App\Gateway\Services\PaymentOrchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private CheckoutOrchestrator $checkout,
        private PaymentOrchestrator $payments,
        private PaymentWriter $paymentWriter,
        private OrderWriter $orderWriter,
    ) {}

    public function index(Request $request): View
    {
        $listDate = $this->listDate($request);

        $orders = Order::query()
            ->with('user')
            ->when($listDate !== null, fn ($q) => $q->whereDate('created_at', $listDate))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $pendingCount = Order::query()
            ->where('channel', OrderChannel::Online)
            ->where('status', OrderStatus::Pending)
            ->count();

        return view('admin.orders.index', compact('orders', 'pendingCount', 'listDate'));
    }

    public function show(Order $order): View
    {
        $order->load('items', 'user', 'bookings.variant.product');

        return view('admin.orders.show', [
            'order' => $order,
            'payments' => $this->paymentWriter->listByOrder($order->id),
        ]);
    }

    public function confirm(Order $order): RedirectResponse
    {
        $this->payments->confirmByShop($order);

        return back()->with('success', 'Đã chốt đơn. Nếu đã thu đủ tiền thì kho được trừ.');
    }

    public function markPaid(Order $order): RedirectResponse
    {
        $this->payments->settleOrder($order);

        return back()->with('success', 'Đã xác nhận thanh toán và trừ kho.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->checkout->cancel($order);

        return back()->with('success', 'Đã hủy đơn.');
    }

    public function complete(Order $order): RedirectResponse
    {
        if (! $order->isSaleOnly()) {
            throw ValidationException::withMessages([
                'status' => ['Đơn có món thuê hoàn tất khi khách trả hết đồ.'],
            ]);
        }

        $this->orderWriter->complete($order);

        return back()->with('success', 'Đã hoàn tất đơn.');
    }

    public function fulfillment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'fulfillment_status' => ['required', 'in:ready_to_pick,delivering,delivered'],
        ]);

        $next = FulfillmentStatus::from($data['fulfillment_status']);
        if ($order->shipping_method?->value !== 'delivery') {
            throw ValidationException::withMessages([
                'fulfillment_status' => ['Chỉ cập nhật giao hàng cho đơn giao nhà.'],
            ]);
        }

        $order->fulfillment_status = $next;
        $order->save();

        if ($next === FulfillmentStatus::Delivered && $order->isSaleOnly()
            && in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing], true)
        ) {
            $this->orderWriter->complete($order);
        }

        return back()->with('success', 'Đã cập nhật trạng thái giao: '.$next->label());
    }

    private function listDate(Request $request): ?string
    {
        $raw = trim((string) $request->input('date', ''));
        if ($raw === 'all') {
            return null;
        }

        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw);
            if ($parsed !== false && $parsed->format('Y-m-d') === $raw && ! $parsed->startOfDay()->isAfter(now()->startOfDay())) {
                return $raw;
            }
        }

        return now()->toDateString();
    }
}
