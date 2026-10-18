<?php

use Illuminate\Console\Scheduling\Schedule;

app()->booted(function () {

    $schedule = app(Schedule::class);

    $schedule->command('cron:khotmul')
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

    // Laravel 12 tidak memakai app/Console/Kernel.php, jadi jadwal
    // notifikasi harus didaftarkan di sini (README 4.4)
    $schedule->call(function () {
        app(\App\Services\NotificationService::class)->run();
    })->name('notifikasi-hafalan')->dailyAt('07:00')->withoutOverlapping();

});