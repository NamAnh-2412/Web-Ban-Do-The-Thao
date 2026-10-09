<?php

namespace App\Domain\Chat\Services;

use App\Domain\Chat\Enums\ChatAttachmentType;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\Message;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function forCustomer(User $customer): Conversation
    {
        if (! $customer->isCustomer()) {
            throw ValidationException::withMessages([
                'user' => ['Chỉ mở hội thoại với tài khoản khách.'],
            ]);
        }

        return Conversation::query()->firstOrCreate(
            ['user_id' => $customer->id],
            ['last_message_at' => now()],
        );
    }

    public function post(
        Conversation $conversation,
        User $author,
        string $body,
        ?string $attachmentType = null,
        ?int $attachmentId = null,
    ): Message {
        $trimmed = trim($body);
        $type = $this->resolveAttachmentType($attachmentType);

        if ($trimmed === '' && $type === null) {
            throw ValidationException::withMessages([
                'body' => ['Vui lòng nhập nội dung hoặc đính kèm sản phẩm/đơn hàng.'],
            ]);
        }

        if ($type !== null && ($attachmentId === null || $attachmentId < 1)) {
            throw ValidationException::withMessages([
                'attachment_id' => ['Vui lòng chọn mục đính kèm.'],
            ]);
        }

        $payload = null;
        if ($type !== null) {
            $payload = match ($type) {
                ChatAttachmentType::Product => $this->snapshotProduct($attachmentId),
                ChatAttachmentType::Order => $this->snapshotOrder($conversation, $attachmentId),
            };
        }

        $preview = $trimmed !== ''
            ? $trimmed
            : $this->attachmentPreview($type, $payload);

        $message = $conversation->messages()->create([
            'user_id' => $author->id,
            'body' => $trimmed,
            'attachment_type' => $type?->value,
            'attachment_id' => $type !== null ? $attachmentId : null,
            'attachment_payload' => $payload,
        ]);

        $conversation->update([
            'last_message' => Str::limit($preview, 120),
            'last_message_at' => now(),
        ]);

        return $message;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function productPickerOptions(int $limit = 50): Collection
    {
        return Product::query()
            ->with('variants')
            ->where('is_active', true)
            ->where('is_suspended', false)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->name,
                'subtitle' => $product->offer_mode->label().($this->productPriceLabel($product) ? ' · '.$this->productPriceLabel($product) : ''),
                'image' => $product->imageSrc(),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function orderPickerOptions(int $customerId, int $limit = 20): Collection
    {
        return Order::query()
            ->with('items')
            ->where('user_id', $customerId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $first = $order->items->first();

                return [
                    'id' => $order->id,
                    'title' => 'Đơn #'.$order->id,
                    'subtitle' => $order->status->label()
                        .' · '.number_format((float) $order->grand_total, 0, ',', '.').'đ'
                        .($first ? ' · '.$first->product_name : ''),
                    'image' => null,
                ];
            });
    }

    public function markReadFor(Conversation $conversation, User $viewer): void
    {
        $conversation->messages()
            ->where('user_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadConversationCountForStore(): int
    {
        return Conversation::query()
            ->whereHas('messages', function ($query) {
                $query->whereNull('read_at')
                    ->whereHas('user', fn ($user) => $user->where('role', UserRole::Customer->value));
            })
            ->count();
    }

    public function unreadCountForCustomer(User $customer): int
    {
        $conversation = Conversation::query()->where('user_id', $customer->id)->first();

        if (! $conversation) {
            return 0;
        }

        return $conversation->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', $customer->id)
            ->count();
    }

    public function inbox(): LengthAwarePaginator
    {
        return Conversation::query()
            ->with('user')
            ->withCount([
                'messages as unread_count' => function ($query) {
                    $query->whereNull('read_at')
                        ->whereHas('user', fn ($user) => $user->where('role', UserRole::Customer->value));
                },
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);
    }

    private function resolveAttachmentType(?string $attachmentType): ?ChatAttachmentType
    {
        if ($attachmentType === null || $attachmentType === '') {
            return null;
        }

        $type = ChatAttachmentType::tryFrom($attachmentType);
        if ($type === null) {
            throw ValidationException::withMessages([
                'attachment_type' => ['Loại đính kèm không hợp lệ.'],
            ]);
        }

        return $type;
    }

    /** @return array<string, mixed> */
    private function snapshotProduct(int $productId): array
    {
        $product = Product::query()->with('variants')->find($productId);
        if ($product === null || ! $product->is_active || $product->is_suspended) {
            throw ValidationException::withMessages([
                'attachment_id' => ['Sản phẩm không tồn tại hoặc đã ngừng bán.'],
            ]);
        }

        return [
            'kind' => ChatAttachmentType::Product->value,
            'id' => $product->id,
            'title' => $product->name,
            'subtitle' => $product->offer_mode->label(),
            'price_label' => $this->productPriceLabel($product),
            'image' => $product->imageSrc(),
            'url' => route('catalog.show', $product->slug),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotOrder(Conversation $conversation, int $orderId): array
    {
        $order = Order::query()->with('items')->find($orderId);
        if ($order === null || (int) $order->user_id !== (int) $conversation->user_id) {
            throw ValidationException::withMessages([
                'attachment_id' => ['Đơn hàng không thuộc hội thoại này.'],
            ]);
        }

        $itemNames = $order->items->take(3)->pluck('product_name')->filter()->values()->all();

        return [
            'kind' => ChatAttachmentType::Order->value,
            'id' => $order->id,
            'title' => 'Đơn #'.$order->id,
            'subtitle' => $order->status->label(),
            'price_label' => number_format((float) $order->grand_total, 0, ',', '.').'đ',
            'items' => $itemNames,
            'image' => null,
            'url' => route('orders.show', $order),
        ];
    }

    private function productPriceLabel(Product $product): ?string
    {
        $sale = $product->variants
            ->pluck('sale_price')
            ->filter(fn ($price) => $price !== null)
            ->map(fn ($price) => (float) $price);
        $rental = $product->variants
            ->pluck('rental_price_per_day')
            ->filter(fn ($price) => $price !== null)
            ->map(fn ($price) => (float) $price);

        $parts = [];
        if ($sale->isNotEmpty()) {
            $min = $sale->min();
            $parts[] = 'từ '.number_format($min, 0, ',', '.').'đ';
        }
        if ($rental->isNotEmpty()) {
            $min = $rental->min();
            $parts[] = 'thuê '.number_format($min, 0, ',', '.').'đ/ngày';
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function attachmentPreview(?ChatAttachmentType $type, ?array $payload): string
    {
        if ($type === null || $payload === null) {
            return '';
        }

        return match ($type) {
            ChatAttachmentType::Product => '[Sản phẩm] '.((string) ($payload['title'] ?? '')),
            ChatAttachmentType::Order => '[Đơn #'.((string) ($payload['id'] ?? '')).']',
        };
    }
}
