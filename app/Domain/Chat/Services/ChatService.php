<?php

namespace App\Domain\Chat\Services;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Models\Message;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function post(Conversation $conversation, User $author, string $body): Message
    {
        $trimmed = trim($body);

        $message = $conversation->messages()->create([
            'user_id' => $author->id,
            'body' => $trimmed,
        ]);

        $conversation->update([
            'last_message' => Str::limit($trimmed, 120),
            'last_message_at' => now(),
        ]);

        return $message;
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
}
