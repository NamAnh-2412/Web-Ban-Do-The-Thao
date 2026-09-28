<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\NotificationType;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'type',
        'channel',
        'subject',
        'body',
        'payload',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'payload' => 'array',
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
