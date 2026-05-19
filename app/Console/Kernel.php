<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Services\RollingJuzService;
use App\Services\NotificationService;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // command utama
        $schedule->command('cron:khotmul')
            ->everyMinute()
            // ->dailyAt('00:00')
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // rolling juz
        $schedule->call(function () {
            app(RollingJuzService::class)->run();
        })->daily();

        // notifikasi
        $schedule->call(function () {
            app(NotificationService::class)->run();
        })->dailyAt('07:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}