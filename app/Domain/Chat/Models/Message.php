<?php

namespace App\Domain\Chat\Models;

use App\Domain\Chat\Enums\ChatAttachmentType;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'attachment_type',
        'attachment_id',
        'attachment_payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_type' => ChatAttachmentType::class,
            'attachment_id' => 'integer',
            'attachment_payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFromStore(): bool
    {
        return $this->user?->isStoreAccount() ?? false;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_type !== null && is_array($this->attachment_payload);
    }
}
