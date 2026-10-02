<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('webthethao:notify-rentals')
    ->dailyAt('08:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

Schedule::command('webthethao:release-expired')
    ->everyFifteenMinutes()
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();
