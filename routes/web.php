<?php

use Illuminate\Support\Facades\Route;

use App\Services\RollingJuzService;
use App\Services\NotificationService;

Route::get('/test-rolling', function () {
    return app(RollingJuzService::class)->run();
});
Route::get('/test-notif', function () {
    app(NotificationService::class)->run();
    return "Notif OK";
});
