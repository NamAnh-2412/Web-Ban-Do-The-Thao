<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Gateway\Services\MomoCheckoutService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MomoCallbackController extends Controller
{
    public function __construct(private MomoCheckoutService $momo) {}

    public function start(Request $request, int $order): RedirectResponse
    {
        $row = Order::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($order);

        try {
            return redirect()->away($this->momo->payUrl($row, forceNew: true));
        } catch (ValidationException $e) {
            return redirect()
                ->route('orders.show', $row->id)
                ->with('error', $e->errors()['payment_method'][0] ?? $e->errors()['order'][0] ?? 'Không tạo được phiên MoMo.');
        }
    }

    public function returned(Request $request): RedirectResponse
    {
        $result = $this->momo->handleCallback($request->all());
        $orderId = is_numeric($request->input('extraData')) ? (int) $request->input('extraData') : null;

        $target = $orderId
            ? redirect()->route('orders.show', $orderId)
            : redirect()->route('orders.index');

        return match ($result) {
            'paid', 'already_paid' => $target->with('status', 'Thanh toán MoMo thành công. Đơn đã được chốt.'),
            'failed' => $target->with('error', 'Giao dịch MoMo thất bại. Bạn có thể thanh toán lại trên cùng đơn.'),
            default => $target->with('error', 'MoMo từ chối (chữ ký hoặc dữ liệu không hợp lệ).'),
        };
    }

    public function ipn(Request $request): JsonResponse
    {
        $this->momo->handleCallback($request->all());

        return response()->json(['message' => 'Received']);
    }
}
