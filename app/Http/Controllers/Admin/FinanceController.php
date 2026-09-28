<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Services\FinanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index(Request $request): View
    {
        [$query, $filters] = $this->finance->filteredOrders($request);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as total_amount')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as paid_amount")
            ->selectRaw("SUM(CASE WHEN payment_status = 'pending' THEN total_price ELSE 0 END) as pending_amount")
            ->first();

        $statusTotals = (clone $query)
            ->select('payment_status')
            ->selectRaw('COUNT(*) as order_count, SUM(total_price) as total_amount')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        $methodTotals = (clone $query)
            ->select('gateway')
            ->selectRaw('COUNT(*) as order_count, SUM(total_price) as total_amount')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as paid_amount")
            ->groupBy('gateway')
            ->get()
            ->keyBy('gateway');

        return view('admin.finance.index', [
            'filters' => $filters,
            'summary' => $summary,
            'statusTotals' => $statusTotals,
            'methodTotals' => $methodTotals,
            'statuses' => FinanceService::STATUSES,
            'methods' => FinanceService::METHODS,
        ]);
    }

    public function transactions(Request $request): View
    {
        [$query, $filters] = $this->finance->filteredOrders($request);
        [$column, $direction] = $this->sort($filters['sort'] ?? 'newest');

        $orders = $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate(15)
            ->withQueryString();

        return view('admin.finance.transactions', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => FinanceService::STATUSES,
            'codTransitions' => FinanceService::COD_TRANSITIONS,
            'methods' => FinanceService::METHODS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$query, $filters] = $this->finance->filteredOrders($request);
        [$column, $direction] = $this->sort($filters['sort'] ?? 'newest');
        $filename = 'finance-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $column, $direction) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã đơn', 'Người nhận', 'Điện thoại', 'Tổng tiền', 'Cổng', 'Trạng thái TT', 'Ngày tạo', 'Ngày thu']);

            foreach ($query->orderBy($column, $direction)->orderBy('id', $direction)->get() as $order) {
                fputcsv($handle, [
                    $order->id,
                    $order->name,
                    $order->phone,
                    $order->total_price,
                    FinanceService::METHODS[$order->gateway] ?? $order->gateway,
                    FinanceService::STATUSES[$order->payment_status] ?? $order->payment_status,
                    $order->created_at,
                    $order->paid_at,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(FinanceService::COD_TRANSITIONS))],
            'current_payment_status' => ['required', 'string'],
            'current_order_status' => ['required', 'string'],
            'current_payment_id' => ['required', 'integer', 'min:0'],
        ], [
            'payment_status.in' => 'Trạng thái COD không hợp lệ.',
            '*.required' => 'Thiếu thông tin trạng thái. Vui lòng tải lại trang.',
        ]);

        $this->finance->updateCodStatus($order, $data, (int) $request->user()->id);

        return back()->with('success', 'Đã lưu trạng thái thanh toán đơn COD #'.$order->id.'.');
    }

    /** @return array{0: string, 1: string} */
    private function sort(string $sort): array
    {
        return match ($sort) {
            'oldest' => ['created_at', 'asc'],
            'amount_asc' => ['total_price', 'asc'],
            'amount_desc' => ['total_price', 'desc'],
            default => ['created_at', 'desc'],
        };
    }
}
