<?php

use Illuminate\Console\Scheduling\Schedule;

app()->booted(function () {

    $schedule = app(Schedule::class);

    $schedule->command('cron:khotmul')
        ->everyMinute()
        ->appendOutputTo(storage_path('logs/scheduler.log'));

});