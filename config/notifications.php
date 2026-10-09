<?php

return [
    /*
    | Days before end_date to send the rental return reminder.
    | Default 1 = email on the morning ~24h before the due date
    | (scheduler runs daily at 08:00 Asia/Ho_Chi_Minh).
    */
    'due_reminder_days' => (int) env('NOTIFICATION_DUE_REMINDER_DAYS', 1),
];
