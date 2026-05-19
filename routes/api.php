<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\AnggotaController;
use App\Http\Controllers\Api\DaurahController;
use App\Http\Controllers\Api\KhatamController;
use App\Http\Controllers\Api\DonasiController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\groupUserController;
use App\Http\Controllers\Api\KhotmulController;
use App\Http\Controllers\Api\KhotmulRecController;
use App\Http\Controllers\Api\KhotmulPeriodeController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\ayahController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\JuzStatsController;
use App\Http\Controllers\Api\JuzController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\HafalanController;
use App\Http\Controllers\Api\LaporanHafalanController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::group(['prefix' => 'v1'], function () {
    // Authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Public API endpoints (if any)
    Route::get('/public-posts', [PostController::class, 'index']);
    Route::get('/ayah-range', [AyahController::class, 'range']);

    Route::get(
        '/laporan-hafalan/{id}',
        [LaporanHafalanController::class, 'laporanByDaurah']
    );
});

// Protected routes (require JWT authentication)
Route::group(['prefix' => 'v1', 'middleware' => 'auth:sanctum'], function () {

    Route::get('/assignments/today', [AssignmentController::class, 'today']);
    Route::put(
        '/khatam/update-status',
        [AssignmentController::class, 'updateStatus']
    );
    Route::post('/hafalan', [HafalanController::class, 'store']);
    Route::get('/hafalan/today', [HafalanController::class, 'today']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/assignment-active', [AssignmentController::class, 'active']);


    // User profile
    Route::get('/profile', [AuthController::class, 'userProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/cekAnggota', [AuthController::class, 'cekAnggota']);
    Route::post('/addUserAnggota', [AuthController::class, 'addUserAnggota']);
    Route::put('/updateUser/{id}', [AuthController::class, 'update']);
    // Posts resource
    Route::apiResource('/posts', PostController::class);
    Route::apiResource('anggota', AnggotaController::class);
    Route::get('/anggota2', [AnggotaController::class, 'index2']);
    Route::get('/indexByGroup', [AnggotaController::class, 'indexByGroup']);
    Route::apiResource('daurah', DaurahController::class);
    Route::get('/periodeDaurah', [DaurahController::class, 'periodeDaurah']);

    Route::get('cekJumlahGroupAnggota', [DaurahController::class, 'cekJumlahGroupAnggota']);
    Route::get('/getDaurahData', [DaurahController::class, 'getDaurahData']);
    Route::get('/reportDaurah', [DaurahController::class, 'reportDaurah']);

    Route::apiResource('khatam', KhatamController::class);
    Route::apiResource('donasi', DonasiController::class);
    Route::apiResource('reward', RewardController::class);
    Route::apiResource('jadwal', JadwalController::class);
    Route::apiResource('groupUser', groupUserController::class);
    Route::get('khotmulPeriode', [khotmulPeriodeController::class, 'index']);
    Route::get('khotmulPeriodeSync', [khotmulPeriodeController::class, 'Sync']);
    Route::get('khotmulPeriodeCreate', [khotmulPeriodeController::class, 'createPeriode']);
    Route::get(
        '/khotmul-periode',
        [KhotmulPeriodeController::class, 'show']
    );
    Route::get(
        '/khotmul-periode/last',
        [KhotmulPeriodeController::class, 'last']
    );
    Route::post(
        '/khotmul-periode',
        [KhotmulPeriodeController::class, 'store']
    );

    Route::put(
        '/khotmul-periode/{id}',
        [KhotmulPeriodeController::class, 'update']
    );

    Route::delete(
        '/khotmul-periode/{id}',
        [KhotmulPeriodeController::class, 'destroy']
    );

    Route::get('/khotmul', [KhotmulController::class, 'index']);
    Route::get('/khotmul/anggota/{anggota_id}', [KhotmulController::class, 'indexAnggotaId']);
    Route::get('/khotmul/generateData', [KhotmulController::class, 'generateData']);
    Route::post('/khotmul/updateStatus/{id}', [KhotmulController::class, 'updateStatus']);
    //Route::post('/khotmul/upload', [KhotmulController::class, 'store'])->name('file.upload');
    Route::get('/khotmul/stats', [KhotmulController::class, 'getAnggotaKhotmulStats']);
    Route::get('/khotmul/stats/group/{group_id}', [KhotmulController::class, 'getAnggotaKhotmulStatsByGroup']);

    // Web Routes
    Route::get('/khotmul/stats/show', [KhotmulController::class, 'showAnggotaKhotmulStats']);

    Route::get('/khotmulrec/{id}', [KhotmulRecController::class, 'index']);
    Route::post('/khotmulrec/upload', [KhotmulRecController::class, 'store'])->name('file.upload');

    Route::post('/messages/send', [MessageController::class, 'send']);
    Route::get('/messages/inbox/{userId}', [MessageController::class, 'inbox']);
    Route::get('/messages/countUnread/{userId}', [MessageController::class, 'countUnread']);
    Route::get('/messages/updateStatus/{id}', [MessageController::class, 'updateStatus']);
    // Other protected resources...
    Route::get('/juz-stats/with-status', [JuzStatsController::class, 'getJuzStatsWithStatus']);
    Route::get('/juz-stats/anggota/{anggota_id}', [JuzStatsController::class, 'getJuzStatsByAnggota']);
    Route::get('/juz-stats/detailed', [JuzStatsController::class, 'getDetailedJuzStats']);
    Route::get('/juz-stats/summary', [JuzStatsController::class, 'getJuzStatsSummary']);

    // Route dengan parameter query
    Route::get('/juz-stats/group/{group_id}', [JuzStatsController::class, 'getJuzStatsWithStatus']);

    Route::get('/juz/{juz?}', [JuzController::class, 'getJuzByNumber']);

    // Get all juz atau filter by query parameter
    Route::get('/juz', [JuzController::class, 'getAllJuz']);

    // Get accurate calculation by juz
    Route::get('/juz-accurate/{juz?}', [JuzController::class, 'getAccurateJuzByNumber']);
});
