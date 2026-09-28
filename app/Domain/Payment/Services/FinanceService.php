<?php

namespace App\Domain\Payment\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentKind;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Gateway\Services\PaymentOrchestrator;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public const STATUSES = [
        'pending' => 'Chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'cancelled' => 'Đã hủy',
        'refunded' => 'Đã hoàn tiền',
    ];

    public const METHODS = [
        'cod' => 'COD',
        'momo' => 'MoMo',
        'bank_transfer' => 'Chuyển khoản',
        'cash' => 'Tiền mặt',
        'unknown' => 'Chưa xác định',
    ];

    public const COD_TRANSITIONS = [
        'pending' => ['pending', 'paid', 'failed'],
        'failed' => ['failed', 'pending', 'paid'],
        'paid' => ['paid', 'refunded'],
        'refunded' => ['refunded'],
        'cancelled' => ['cancelled'],
    ];

    public function __construct(
        private PaymentOrchestrator $payments,
        private PaymentWriter $writer,
    ) {}

    /**
     * @return array{0: Builder, 1: array<string, mixed>}
     */
    public function filteredOrders(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', ...($request->filled('min_amount') ? ['gte:min_amount'] : [])],
            'gateway' => ['nullable', Rule::in(array_keys(self::METHODS))],
            'payment_status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'amount_asc', 'amount_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'date_to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'max_amount.gte' => 'Số tiền tối đa phải lớn hơn hoặc bằng số tiền tối thiểu.',
            '*.date_format' => 'Ngày lọc không hợp lệ (định dạng năm-tháng-ngày).',
            '*.numeric' => 'Số tiền phải là một giá trị số.',
            '*.min' => 'Giá trị bộ lọc nhỏ hơn mức cho phép.',
            '*.in' => 'Giá trị bộ lọc không hợp lệ.',
        ]);

        $query = $this->ordersQuery()->where('created_at', '<=', now());

        if ($request->filled('search')) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');

                if (preg_match('/^(?:#|DH)?0*(\d+)$/i', $search, $matches)) {
                    $inner->orWhere('id', $matches[1]);
                }
            });
        }

        foreach (['gateway', 'payment_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $filters[$field]);
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }

        foreach (['min_amount' => '>=', 'max_amount' => '<='] as $field => $operator) {
            if ($request->filled($field)) {
                $query->where('total_price', $operator, $filters[$field]);
            }
        }

        return [$query, $filters];
    }

    public function updateCodStatus(Order $order, array $data, int $staffId): void
    {
        DB::transaction(function () use ($order, $data, $staffId) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $this->representativePayment($order, true);

            $isCod = $payment?->method === PaymentMethod::Cod;
            if (! $isCod) {
                throw ValidationException::withMessages([
                    'payment_status' => ['Chỉ được cập nhật thủ công cho đơn COD.'],
                ]);
            }

            $currentStatus = $this->financeStatus($order, $payment);

            if (
                $currentStatus !== $data['current_payment_status']
                || $order->status->value !== $data['current_order_status']
                || (int) ($payment?->id ?? 0) !== (int) $data['current_payment_id']
            ) {
                throw ValidationException::withMessages([
                    'payment_status' => ['Đơn hàng vừa thay đổi. Vui lòng tải lại trang trước khi cập nhật.'],
                ]);
            }

            $newStatus = $data['payment_status'];
            if (! in_array($newStatus, self::COD_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => ['Không thể chuyển sang trạng thái thanh toán này.'],
                ]);
            }

            if ($newStatus === $currentStatus) {
                return;
            }

            if (
                in_array($newStatus, ['pending', 'paid'], true)
                && $order->status === OrderStatus::Cancelled
            ) {
                throw ValidationException::withMessages([
                    'payment_status' => ['Không thể xác nhận thu tiền cho đơn đã hủy.'],
                ]);
            }

            $note = 'Đối soát tài chính (NV #'.$staffId.'): '.self::STATUSES[$newStatus];

            match ($newStatus) {
                'paid' => $this->payments->captureGateway($order, null, $note),
                'failed' => $this->failCollectibles($order, $note),
                'pending' => $this->reopenCollectibles($order),
                'refunded' => $this->writer->refundCompletedCollections($order->id, (int) $order->user_id, $note),
                default => null,
            };
        });
    }

    public function ordersQuery(): Builder
    {
        $paymentId = Payment::query()
            ->select('id')
            ->whereColumn('order_id', 'orders.id')
            ->whereIn('kind', [PaymentKind::Merchandise->value, PaymentKind::Deposit->value])
            ->orderByRaw("CASE WHEN status = 'completed' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(1);

        $orders = DB::table('orders')
            ->leftJoin('payments as payment', function ($join) use ($paymentId) {
                $join->on('payment.order_id', '=', 'orders.id')
                    ->where('payment.id', '=', $paymentId);
            })
            ->select(
                'orders.id',
                'orders.status',
                'orders.created_at',
                'orders.grand_total as total_price',
                'orders.shipping_name as name',
                'orders.shipping_phone as phone',
                'payment.id as payment_id',
                'payment.paid_at',
            )
            ->selectRaw("COALESCE(payment.method, 'unknown') as gateway")
            ->selectRaw($this->statusSelect());

        return DB::query()->fromSub($orders, 'finance_orders');
    }

    private function statusSelect(): string
    {
        $collectible = "kind IN ('merchandise', 'deposit')";

        return "CASE
            WHEN orders.status = 'cancelled' THEN 'cancelled'
            WHEN EXISTS (
                SELECT 1 FROM payments r
                WHERE r.order_id = orders.id AND r.kind = 'refund' AND r.status = 'completed'
            ) AND NOT EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status = 'pending'
            ) AND EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status = 'completed'
            ) THEN 'refunded'
            WHEN EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status = 'completed'
            ) AND NOT EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status = 'pending'
            ) THEN 'paid'
            WHEN EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status = 'failed'
            ) AND NOT EXISTS (
                SELECT 1 FROM payments p
                WHERE p.order_id = orders.id AND {$collectible} AND p.status IN ('pending', 'completed')
            ) THEN 'failed'
            ELSE 'pending'
        END as payment_status";
    }

    private function representativePayment(Order $order, bool $lock): ?Payment
    {
        $query = $order->payments()
            ->whereIn('kind', [PaymentKind::Merchandise, PaymentKind::Deposit])
            ->orderByRaw("CASE WHEN status = 'completed' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function financeStatus(Order $order, ?Payment $payment): string
    {
        if ($order->status === OrderStatus::Cancelled) {
            return 'cancelled';
        }

        $collectible = $order->payments()
            ->whereIn('kind', [PaymentKind::Merchandise, PaymentKind::Deposit])
            ->get();
        $refunded = $order->payments()
            ->where('kind', PaymentKind::Refund)
            ->where('status', PaymentStatus::Completed)
            ->exists();

        if ($refunded && $collectible->contains(fn (Payment $row) => $row->status === PaymentStatus::Completed)
            && $collectible->every(fn (Payment $row) => $row->status !== PaymentStatus::Pending)
        ) {
            return 'refunded';
        }

        if ($collectible->isNotEmpty() && $collectible->every(fn (Payment $row) => $row->status === PaymentStatus::Completed)) {
            return 'paid';
        }

        if ($collectible->contains(fn (Payment $row) => $row->status === PaymentStatus::Failed)
            && $collectible->every(fn (Payment $row) => ! in_array($row->status, [PaymentStatus::Pending, PaymentStatus::Completed], true))
        ) {
            return 'failed';
        }

        return 'pending';
    }

    private function failCollectibles(Order $order, string $note): void
    {
        foreach ($order->payments()->whereIn('kind', [PaymentKind::Merchandise, PaymentKind::Deposit])->where('status', PaymentStatus::Pending)->get() as $row) {
            $this->writer->fail($row, $note);
        }
    }

    private function reopenCollectibles(Order $order): void
    {
        foreach ($order->payments()->whereIn('kind', [PaymentKind::Merchandise, PaymentKind::Deposit])->where('status', PaymentStatus::Failed)->get() as $row) {
            $this->writer->reopenFailed($row);
        }
    }
}
