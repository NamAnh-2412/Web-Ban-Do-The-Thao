<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentQrSetting;
use App\Domain\Payment\Support\BankTransferQr;
use App\Gateway\Services\PaymentOrchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private PaymentOrchestrator $orchestrator) {}

    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.payments.index', [
            'payments' => $payments,
            'qr' => BankTransferQr::display(),
        ]);
    }

    public function saveQr(Request $request): RedirectResponse
    {
        $row = PaymentQrSetting::current();
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'qr_image' => [$row?->qr_path ? 'nullable' : 'required', 'image', 'max:2048'],
        ], [
            'bank_name.required' => 'Nhập tên ngân hàng hoặc ví.',
            'account_name.required' => 'Nhập tên chủ tài khoản.',
            'qr_image.required' => 'Tải ảnh QR chuyển khoản.',
            'qr_image.image' => 'File QR phải là ảnh.',
        ]);

        if ($file = $request->file('qr_image')) {
            $path = $file->store('payment-qr', 'public');
            $row?->deleteStoredImage();
            $data['qr_path'] = $path;
        }
        unset($data['qr_image']);
        $data['account_number'] = (string) ($data['account_number'] ?? '');

        if ($row) {
            $row->update($data);
        } else {
            PaymentQrSetting::query()->create($data);
        }

        return back()->with('success', 'Đã lưu QR chuyển khoản.');
    }

    public function confirm(Payment $payment): RedirectResponse
    {
        $this->orchestrator->confirmReceived($payment);

        return back()->with('success', 'Đã xác nhận nhận tiền.');
    }

    public function refund(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['required', 'string', 'max:500'],
        ]);
        $order = Order::query()->with(['items', 'bookings'])->findOrFail($data['order_id']);
        if ($order->canBeCancelled() && $order->status->canCancelAfterPayment()) {
            throw ValidationException::withMessages([
                'order_id' => ['Đơn này hãy hủy để hoàn đủ tiền và cộng kho.'],
            ]);
        }
        $this->orchestrator->refund($order->id, (int) $order->user_id, (float) $data['amount'], $data['note']);

        return back()->with('success', 'Đã lập khoản hoàn.');
    }
}
