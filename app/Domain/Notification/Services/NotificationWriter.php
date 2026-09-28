<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Jobs\SendOutboundEmail;
use App\Domain\Notification\Mail\OutboundMail;
use App\Domain\Notification\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationWriter
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Notification, 1: bool} [row, created]
     */
    public function enqueueOnce(
        int $userId,
        string $email,
        NotificationType $type,
        string $subject,
        string $body,
        array $payload,
    ): array {
        $key = (string) ($payload['dedupe_key'] ?? '');
        if ($key === '') {
            $payload['dedupe_key'] = $type->value.':'.$userId.':'.uniqid();
            $key = $payload['dedupe_key'];
        }

        $existing = Notification::query()
            ->where('type', $type)
            ->where('payload->dedupe_key', $key)
            ->first();

        if ($existing) {
            if ($existing->status === NotificationStatus::Queued) {
                SendOutboundEmail::dispatch($existing->id);
            }

            return [$existing, false];
        }

        $row = Notification::query()->create([
            'user_id' => $userId,
            'email' => $email,
            'type' => $type,
            'channel' => NotificationChannel::Email,
            'subject' => $subject,
            'body' => $body,
            'payload' => $payload,
            'status' => NotificationStatus::Queued,
        ]);

        SendOutboundEmail::dispatch($row->id);

        return [$row, true];
    }

    public function deliver(Notification $row): Notification
    {
        if ($row->status === NotificationStatus::Sent) {
            return $row;
        }

        try {
            Mail::to($row->email)->send(new OutboundMail($row->subject, $row->body));
            $row->status = NotificationStatus::Sent;
            $row->sent_at = now();
            $row->error_message = null;
            $row->save();
        } catch (Throwable $e) {
            $row->status = NotificationStatus::Failed;
            $row->error_message = $e->getMessage();
            $row->save();
        }

        return $row->refresh();
    }

    /** @return Collection<int, Notification> */
    public function listByUser(int $userId): Collection
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }
}
