<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically reopen scheduled work orders when their scheduled date/time is reached
Schedule::command('work-orders:reopen-scheduled')->everyMinute();
