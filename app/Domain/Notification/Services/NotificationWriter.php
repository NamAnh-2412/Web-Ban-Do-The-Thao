<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\NotificationType;
use App\Domain\Notification\Jobs\SendOutboundEmail;
use App\Domain\Notification\Mail\OutboundMail;
use App\Domain\Notification\Models\Notification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
            $payload['dedupe_key'] = $type->value.':'.$userId.':'.uniqid('', true);
            $key = $payload['dedupe_key'];
        }

        $existing = Notification::query()->where('dedupe_key', $key)->first();
        if ($existing === null) {
            $existing = Notification::query()
                ->where('type', $type)
                ->where('payload->dedupe_key', $key)
                ->first();
        }

        if ($existing) {
            return [$this->requeueIfNeeded($existing), false];
        }

        try {
            $row = Notification::query()->create([
                'user_id' => $userId,
                'email' => $email,
                'type' => $type,
                'channel' => NotificationChannel::Email,
                'subject' => $subject,
                'body' => $body,
                'payload' => $payload,
                'dedupe_key' => $key,
                'status' => NotificationStatus::Queued,
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = Notification::query()->where('dedupe_key', $key)->firstOrFail();

            return [$this->requeueIfNeeded($row), false];
        }

        SendOutboundEmail::dispatch($row->id);
        Log::info('notification.enqueued', [
            'notification_id' => $row->id,
            'type' => $type->value,
            'dedupe_key' => $key,
            'user_id' => $userId,
        ]);

        return [$row, true];
    }

    public function deliver(Notification $row): Notification
    {
        if ($row->status === NotificationStatus::Sent) {
            return $row;
        }

        try {
            Mail::to($row->email)->send(OutboundMail::fromNotification($row));
            $row->status = NotificationStatus::Sent;
            $row->sent_at = now();
            $row->error_message = null;
            $row->save();

            Log::info('notification.email.sent', [
                'notification_id' => $row->id,
                'type' => $row->type->value,
                'dedupe_key' => $row->dedupe_key,
                'email' => $row->email,
            ]);
        } catch (Throwable $e) {
            $row->status = NotificationStatus::Failed;
            $row->error_message = $e->getMessage();
            $row->save();

            Log::error('notification.email.failed', [
                'notification_id' => $row->id,
                'type' => $row->type->value,
                'dedupe_key' => $row->dedupe_key,
                'email' => $row->email,
                'error' => $e->getMessage(),
            ]);
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

    private function requeueIfNeeded(Notification $existing): Notification
    {
        if ($existing->status === NotificationStatus::Queued) {
            SendOutboundEmail::dispatch($existing->id);

            return $existing;
        }

        if ($existing->status === NotificationStatus::Failed) {
            $existing->status = NotificationStatus::Queued;
            $existing->error_message = null;
            $existing->save();
            SendOutboundEmail::dispatch($existing->id);

            Log::info('notification.requeued_after_failure', [
                'notification_id' => $existing->id,
                'dedupe_key' => $existing->dedupe_key,
                'type' => $existing->type->value,
            ]);
        }

        return $existing->refresh();
    }
}
