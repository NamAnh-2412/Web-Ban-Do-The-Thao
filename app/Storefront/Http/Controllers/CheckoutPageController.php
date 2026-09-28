<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Payment\Services\PaymentWriter;
use App\Domain\Payment\Support\BankTransferQr;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\Rental\Services\RentalSession;
use App\Domain\Rental\Services\RentalWriter;
use App\Domain\Review\Services\ReviewWriter;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Shipping\Enums\ShippingMethod;
use App\Gateway\Services\CheckoutOrchestrator;
use App\Gateway\Services\MomoCheckoutService;
use App\Gateway\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Storefront\Support\CartPresenter;
use App\Storefront\Support\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutPageController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CartPresenter $presenter,
        private CheckoutOrchestrator $checkout,
        private PaymentWriter $payments,
        private ReviewWriter $reviews,
        private RentalWriter $rentals,
        private MomoCheckoutService $momo,
        private ShippingService $shipping,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->lines() === []) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng trống.');
        }

        return view('storefront.checkout', $this->presenter->viewData() + [
            'bank' => BankTransferQr::display(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'regex:/^0\d{9}$/'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_method' => ['nullable', 'in:pickup,delivery'],
            'to_province_id' => ['nullable', 'integer'],
            'to_district_id' => ['nullable', 'integer'],
            'to_ward_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'in:bank_transfer,momo,cod'],
        ], [
            'shipping_name.required' => 'Vui lòng nhập họ tên.',
            'shipping_phone.required' => 'Vui lòng nhập điện thoại.',
            'shipping_phone.regex' => 'Số điện thoại phải 10 số, bắt đầu bằng 0.',
            'shipping_address.required' => 'Vui lòng nhập địa chỉ.',
        ]);

        if ($this->cart->lines() === []) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng trống.');
        }

        $method = $data['payment_method'] ?? PaymentMethod::BankTransfer->value;
        $shippingMethod = $data['shipping_method'] ?? ShippingMethod::Pickup->value;

        $order = $this->checkout->checkout($request->user(), [
            'channel' => 'online',
            ...$data,
            'payment_method' => $method,
            'shipping_method' => $shippingMethod,
            'lines' => $this->presenter->checkoutLines(),
        ]);

        $this->cart->clear();
        $request->session()->forget('checkout_coupon');

        if ($method === PaymentMethod::Cod->value && $order->isDelivery()) {
            $this->shipping->createShipment($order, alreadyPaid: false);
        }

        if ($method === PaymentMethod::Momo->value) {
            try {
                return redirect()->away($this->momo->payUrl($order));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect()
                    ->route('orders.show', $order->id)
                    ->with('error', $e->errors()['payment_method'][0] ?? 'Đã đặt hàng nhưng chưa mở được MoMo. Dùng Thanh toán lại.');
            }
        }

        return redirect()->route('orders.show', $order->id)->with('status', 'Đã đặt hàng. Tồn kho đã khóa, chờ cửa hàng xác nhận.');
    }

    public function orders(Request $request): View
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('id')
            ->paginate(10);

        return view('storefront.orders.index', ['orders' => $orders]);
    }

    public function orderShow(Request $request, int $order): View
    {
        $row = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.review', 'bookings.variant.product', 'bookings.extensions', 'bookings.incidents'])
            ->findOrFail($order);

        return view('storefront.orders.show', [
            'order' => $row,
            'payments' => $this->payments->listByOrder($row->id),
            'bookings' => $row->bookings,
            'rentalSessions' => RentalSession::group($row->bookings),
            'bankQr' => BankTransferQr::forOrder($row->id),
            'depositOutcomes' => $row->bookings->mapWithKeys(
                fn ($booking) => [$booking->id => $this->rentals->depositOutcome($booking)]
            ),
        ]);
    }

    public function requestExtension(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:rental_bookings,id'],
            'new_end_date' => ['required', 'date'],
        ]);

        $row = Order::query()->where('user_id', $request->user()->id)->findOrFail($order);
        $booking = RentalBooking::query()
            ->where('order_id', $row->id)
            ->whereKey($data['booking_id'])
            ->firstOrFail();

        $this->rentals->requestExtensionForSession($booking, $data['new_end_date']);

        return back()->with('status', 'Đã gửi yêu cầu gia hạn cho buổi thuê. Cửa hàng sẽ thu thêm rồi duyệt.');
    }

    public function review(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Vui lòng chọn số sao.',
        ]);

        $row = Order::query()->where('user_id', $request->user()->id)->findOrFail($order);
        $item = OrderItem::query()->where('order_id', $row->id)->findOrFail($data['order_item_id']);

        $this->reviews->create($request->user(), $row, $item, (int) $data['rating'], $data['comment'] ?? null);

        return back()->with('status', 'Đã gửi đánh giá.');
    }
}
