<?php

namespace App\Console\Commands;

use App\Domain\Rental\Services\RentalWriter;
use App\Gateway\Services\NotificationOrchestrator;
use Illuminate\Console\Command;

class NotifyRentalsCommand extends Command
{
    protected $signature = 'webthethao:notify-rentals
                            {--date= : Ngày chạy Y-m-d (mặc định hôm nay)}';

    protected $description = 'Queue email nhắc hạn trả đồ và quá hạn (Notification service)';

    public function handle(NotificationOrchestrator $notifications, RentalWriter $rentals): int
    {
        $overdueMarked = $rentals->markOverdue();
        $date = $this->option('date');
        $result = $notifications->dispatchRentalCycle(is_string($date) && $date !== '' ? $date : null);

        $this->info("Đánh dấu quá hạn: {$overdueMarked}. Nhắc hạn: {$result['due_reminders']}. Quá hạn mail: {$result['overdue']}.");

        return self::SUCCESS;
    }
}
