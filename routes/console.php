<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync biometric device logs, push to Frappe HR, and trigger attendance calculations
Schedule::command('attendance:auto-sync --fetch-devices --trigger-attendance')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
