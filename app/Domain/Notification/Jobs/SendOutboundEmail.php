<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Services\NotificationWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOutboundEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationId) {}

    public function handle(NotificationWriter $writer): void
    {
        $row = Notification::query()->find($this->notificationId);
        if ($row === null || $row->status === NotificationStatus::Sent) {
            return;
        }

        $writer->deliver($row);
    }
}
