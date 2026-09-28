<?php

namespace App\Domain\Review\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Review\Enums\ReviewKind;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReviewWriter
{
    public function create(User $user, Order $order, OrderItem $item, int $rating, ?string $comment): Review
    {
        if ((int) $order->user_id !== (int) $user->id || (int) $item->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Dòng đơn không thuộc về bạn.'],
            ]);
        }

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'order' => ['Chỉ đánh giá sau khi đơn đã thanh toán.'],
            ]);
        }

        if (Review::query()->where('user_id', $user->id)->where('order_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Bạn đã đánh giá dòng này.'],
            ]);
        }

        return Review::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'kind' => ReviewKind::from($item->line_type->value),
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    /** @return Collection<int, Review> */
    public function listByProduct(int $productId): Collection
    {
        return Review::query()
            ->with('user')
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->get();
    }
}
