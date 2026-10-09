<?php

namespace App\Console\Commands;

use App\Gateway\Services\NotificationOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyRentalsCommand extends Command
{
    protected $signature = 'webthethao:notify-rentals
                            {--date= : Ngày chạy Y-m-d (mặc định hôm nay)}';

    protected $description = 'Queue email nhắc hạn trả đồ và quá hạn (Notification service)';

    public function handle(NotificationOrchestrator $notifications): int
    {
        $date = $this->option('date');
        $runDate = is_string($date) && $date !== '' ? $date : now()->toDateString();

        try {
            $result = $notifications->dispatchRentalCycle($runDate);
        } catch (Throwable $e) {
            Log::error('notification.rental_cycle.command_failed', [
                'run_date' => $runDate,
                'error' => $e->getMessage(),
            ]);
            $this->error('Lỗi chu kỳ nhắc thuê: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Nhắc hạn trả: %d. Quá hạn mail: %d. (ngày chạy %s, lead %d ngày)',
            $result['due_reminders'],
            $result['overdue'],
            $runDate,
            (int) config('notifications.due_reminder_days', 1),
        ));

        return self::SUCCESS;
    }
}
