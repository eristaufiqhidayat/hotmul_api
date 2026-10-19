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
use App\Http\Controllers\Api\BadalController;
use App\Http\Controllers\Api\KasController;
use App\Http\Controllers\Api\DokumentasiController;
use App\Http\Controllers\Api\JadwalBergulirController;
use App\Http\Controllers\Api\DeviceTokenController;
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

    // Public API endpoints (if any)
    Route::get('/public-posts', [PostController::class, 'index']);
    Route::get('/ayah-range', [AyahController::class, 'range']);
});

// Protected routes (require JWT authentication)
Route::group(['prefix' => 'v1', 'middleware' => 'auth:sanctum'], function () {

    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::get(
        '/laporan-hafalan/{id}',
        [LaporanHafalanController::class, 'laporanByDaurah']
    );

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
    Route::apiResource('anggota', AnggotaController::class)->only(['index', 'show']);
    Route::get('/anggota2', [AnggotaController::class, 'index2']);
    Route::get('/indexByGroup', [AnggotaController::class, 'indexByGroup']);
    Route::apiResource('daurah', DaurahController::class)->only(['index', 'show']);
    Route::get('/periodeDaurah', [DaurahController::class, 'periodeDaurah']);

    Route::get('cekJumlahGroupAnggota', [DaurahController::class, 'cekJumlahGroupAnggota']);
    Route::get('/getDaurahData', [DaurahController::class, 'getDaurahData']);
    Route::get('/reportDaurah', [DaurahController::class, 'reportDaurah']);

    Route::apiResource('khatam', KhatamController::class)->only(['index', 'show']);
    // anggota boleh mencatat donasinya sendiri; ubah/hapus hanya admin
    Route::apiResource('donasi', DonasiController::class)->only(['index', 'show', 'store']);
    Route::apiResource('reward', RewardController::class)->only(['index', 'show']);
    Route::apiResource('jadwal', JadwalController::class)->only(['index', 'show']);
    Route::apiResource('groupUser', groupUserController::class)->only(['index', 'show']);
    Route::get('khotmulPeriode', [KhotmulPeriodeController::class, 'index']);
    // khotmulPeriodeSync (TRUNCATE khotmul_periode) dan khotmulPeriodeCreate
    // dihapus: periode dibuat otomatis oleh cron:khotmul (KhotmulService).
    Route::get(
        '/khotmul-periode',
        [KhotmulPeriodeController::class, 'show']
    );
    Route::get(
        '/khotmul-periode/last',
        [KhotmulPeriodeController::class, 'last']
    );

    Route::get('/khotmul', [KhotmulController::class, 'index']);
    Route::get('/khotmul/anggota/{anggota_id}', [KhotmulController::class, 'indexAnggotaId']);
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

    // Jadwal bergulir (FR-02)
    Route::get('/jadwal-bergulir', [JadwalBergulirController::class, 'index']);

    // Badal + badge Pengganti Aktif (FR-06, FR-07)
    Route::get('/badal', [BadalController::class, 'index']);
    Route::post('/badal/{assignment}/ambil', [BadalController::class, 'ambil']);
    Route::post('/badal/{id}/batal', [BadalController::class, 'batal']);
    Route::get('/badges', [BadalController::class, 'badges']);

    // Kas grup (FR-09)
    Route::get('/kas', [KasController::class, 'index']);
    Route::get('/kas/export', [KasController::class, 'export']);
    Route::post('/kas', [KasController::class, 'store']);
    Route::delete('/kas/{id}', [KasController::class, 'destroy']);

    // Dokumentasi khotaman (FR-08)
    Route::get('/dokumentasi', [DokumentasiController::class, 'index']);
    Route::post('/dokumentasi', [DokumentasiController::class, 'store']);
    Route::delete('/dokumentasi/{id}', [DokumentasiController::class, 'destroy']);

    // Token FCM untuk push notification (FR-04)
    Route::post('/device-token', [DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
});

// Admin only (users.role = admin)
Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum', 'admin']], function () {
    Route::apiResource('anggota', AnggotaController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('daurah', DaurahController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('khatam', KhatamController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('donasi', DonasiController::class)->only(['update', 'destroy']);
    Route::apiResource('reward', RewardController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('jadwal', JadwalController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('groupUser', groupUserController::class)->only(['store', 'update', 'destroy']);

    Route::post('/khotmul-periode', [KhotmulPeriodeController::class, 'store']);
    Route::put('/khotmul-periode/{id}', [KhotmulPeriodeController::class, 'update']);
    Route::delete('/khotmul-periode/{id}', [KhotmulPeriodeController::class, 'destroy']);

    Route::get('/khotmul/generateData', [KhotmulController::class, 'generateData']);

    Route::post('/kas/{id}/verifikasi', [KasController::class, 'verifikasi']);
});
