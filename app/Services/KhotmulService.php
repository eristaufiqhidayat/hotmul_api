<?php

namespace App\Services;

use App\Models\User;
use App\Models\JuzAssignment;
use App\Models\LogsCrond;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;


class KhotmulService
{
    public function runCron()
    {
        Log::info('Cron khotmul START', ['time' => now()]);

        Cache::lock('cron-khotmul', 10)->block(5, function () {

            // 🔥 ambil periode active (GLOBAL)
            $periode = DB::table('khotmul_periode')
                ->where('status', 'active')
                ->first();

            // ===============================
            // CASE 1: BELUM ADA PERIODE
            // ===============================
            if (!$periode) {

                Log::warning('Belum ada periode → buat pertama');

                $this->createNextPeriode();
                return;
            }

            // ===============================
            // CASE 2: SUDAH SELESAI
            // ===============================
            if (!empty($periode->tanggal_selesai) && now()->gt($periode->tanggal_selesai)) {

                Log::info('Periode selesai → generate baru', [
                    'periode' => $periode->periode
                ]);

                DB::table('khotmul_periode')
                    ->where('id', $periode->id)
                    ->update([
                        'status' => 'done',
                        'updated_at' => now()
                    ]);

                $this->createNextPeriode();
            } else {

                Log::info('Periode masih berjalan', [
                    'periode' => $periode->periode
                ]);
            }
        });

        Log::info('Cron khotmul END', ['time' => now()]);
    }
    public function runPushperiode($periodeId)
    {
        Log::info('Cron khotmul START', ['time' => now()]);

        $periode = DB::table('khotmul_periode')
            ->where('periode', $periodeId)
            ->where('status', 'active')
            ->first();
        Log::info('DEBUG PARAM', [
            'periodeId' => $periodeId,
            'type' => gettype($periodeId),
        ]);
        if (!$periode) {
            Log::warning('Periode tidak ditemukan / tidak aktif', ['id' => $periodeId]);
            return;
        }

        $start = Carbon::parse($periode->tanggal_mulai);
        $end = Carbon::parse($periode->tanggal_selesai);

        $this->generateAssignments($periodeId, $start, $end);

        Log::info('Cron khotmul END', ['time' => now()]);
    }
    /**
     * Lama satu periode (README: 1 juz per 2 pekan).
     */
    public const HARI_PER_PERIODE = 14;

    /**
     * Maksimal anggota per grup (README: 30 anggota, 1 anggota = 1 juz).
     */
    public const MAKS_ANGGOTA = 30;

    public function runNextPeriode()
    {
        LogsCrond::create([
            'date' => now(),
            'message' => 'RUN NEXT PERIODE START'
        ]);
        Log::info('RUN NEXT PERIODE START', [
            'time' => now()
        ]);

        DB::transaction(function () {

            // =========================================
            // AMBIL PERIODE TERAKHIR
            // =========================================
            $lastPeriode = DB::table('khotmul_periode')
                ->orderByDesc('periode')
                ->first();

            // =========================================
            // JIKA BELUM ADA PERIODE
            // =========================================
            if (!$lastPeriode) {

                Log::warning('Belum ada periode');

                $start = Carbon::today();
                $end = $start->copy()->addDays(self::HARI_PER_PERIODE - 1);

                DB::table('khotmul_periode')->insert([
                    'periode' => 1,
                    'tanggal' => now(),
                    'tanggal_mulai' => $start,
                    'tanggal_selesai' => $end,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // periode pertama juga langsung dibagi juz-nya
                $this->assignJuz(null, 1, $start, $end);

                Log::info('Periode pertama berhasil dibuat');

                return;
            }

            // =========================================
            // CEK APAKAH PERIODE SUDAH BERAKHIR
            // =========================================
            // Periode dianggap berjalan sampai akhir hari tanggal_selesai.
            // Periode yang belum mulai (tanggal_mulai di masa depan) juga
            // bukan alasan untuk membuat periode baru.
            $selesai = Carbon::parse($lastPeriode->tanggal_selesai);

            if (now()->lte($selesai->copy()->endOfDay())) {

                Log::info('Periode masih aktif', [
                    'periode' => $lastPeriode->periode,
                    'tanggal_selesai' => $selesai,
                ]);
                return;
            }

            // =========================================
            // UPDATE PERIODE LAMA
            // =========================================
            DB::table('khotmul_periode')
                ->where('id', $lastPeriode->id)
                ->update([
                    'status' => 'done',
                    'updated_at' => now(),
                ]);

            // =========================================
            // BUAT PERIODE BARU
            // =========================================
            $periodeBaru = $lastPeriode->periode + 1;

            $startBaru = $selesai->copy()->startOfDay()->addDay();

            // kalau cron sempat mati lama, mulai dari hari ini
            // supaya tidak membuat banyak periode yang sudah lewat
            if ($startBaru->lt(Carbon::today())) {
                $startBaru = Carbon::today();
            }

            $endBaru = $startBaru->copy()->addDays(self::HARI_PER_PERIODE - 1);

            DB::table('khotmul_periode')->insert([
                'periode' => $periodeBaru,
                'tanggal' => now(),
                'tanggal_mulai' => $startBaru,
                'tanggal_selesai' => $endBaru,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Periode baru berhasil dibuat', [
                'periode' => $periodeBaru
            ]);

            // =========================================
            // GENERATE ASSIGNMENT BARU
            // =========================================
            $this->newPeriode($lastPeriode->periode, $periodeBaru, $startBaru, $endBaru);
        });

        Log::info('RUN NEXT PERIODE END', [
            'time' => now()
        ]);
    }
    public function newPeriode($periodeBefore, $periodeBaru, $start, $end)
    {
        // juz yang belum selesai sampai akhir periode → terlambat
        $updated = DB::table('juz_assignments')
            ->where('status', 'active')
            ->update([
                'status' => 'late',
                'updated_at' => now(),
            ]);

        Log::info('Assignment ditandai late', ['total' => $updated]);

        $this->assignJuz($periodeBefore, $periodeBaru, $start, $end);
    }

    /**
     * Bagi juz untuk satu periode ke setiap grup.
     *
     * - Anggota yang sudah punya juz di periode sebelumnya bergeser ke
     *   juz berikutnya (30 kembali ke 1).
     * - Anggota baru di grup mendapat juz yang masih kosong.
     * - Grup tidak perlu lengkap 30 orang; maksimal 30 anggota yang dapat juz.
     */
    public function assignJuz($periodeBefore, $periode, $start, $end)
    {
        $groupIds = User::whereNotNull('group_id')
            ->where('group_id', '>', 0)
            ->distinct()
            ->pluck('group_id');

        foreach ($groupIds as $groupId) {

            $exists = JuzAssignment::where('group_id', $groupId)
                ->where('period_index', $periode)
                ->exists();

            if ($exists) {
                Log::warning('Periode sudah ada, skip', [
                    'group_id' => $groupId,
                    'periode' => $periode
                ]);
                continue;
            }

            $memberIds = User::where('group_id', $groupId)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $previous = $periodeBefore
                ? JuzAssignment::where('group_id', $groupId)
                    ->where('period_index', $periodeBefore)
                    ->whereNotNull('user_id')
                    ->pluck('juz_number', 'user_id')
                    ->all()
                : [];

            $plan = [];
            foreach ($memberIds as $userId) {
                if (isset($previous[$userId])) {
                    $plan[$userId] = $previous[$userId] % 30 + 1;
                }
            }

            $free = array_values(array_diff(range(1, 30), $plan));
            $newcomers = array_values(array_diff($memberIds, array_keys($plan)));
            shuffle($newcomers);

            foreach ($newcomers as $userId) {
                if (empty($free)) {
                    Log::warning('Grup melebihi 30 anggota, sisa anggota tidak mendapat juz', [
                        'group_id' => $groupId,
                        'user_id' => $userId,
                    ]);
                    break;
                }
                $plan[$userId] = array_shift($free);
            }

            foreach ($plan as $userId => $juz) {
                JuzAssignment::create([
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'juz_number' => $juz,
                    'period_index' => $periode,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'active',
                ]);
            }

            Log::info('Generate juz', [
                'group_id' => $groupId,
                'periode' => $periode,
                'total' => count($plan),
            ]);
        }
    }
    public function createNextPeriode()
    {
        DB::transaction(function () {

            // 🔥 pastikan tidak ada active
            $activeExists = DB::table('khotmul_periode')
                ->where('status', 'active')
                ->exists();

            if ($activeExists) {
                Log::warning('Masih ada periode active, skip');
                return;
            }

            // ambil periode terakhir
            $last = DB::table('khotmul_periode')
                ->orderByDesc('periode')
                ->first();

            $periodeBaru = $last ? $last->periode + 1 : 1;

            $start = $last
                ? Carbon::parse($last->tanggal_selesai)->addDay()
                : now();

            $end = (clone $start)->addDays(6);

            // ===============================
            // INSERT PERIODE GLOBAL
            // ===============================
            DB::table('khotmul_periode')->insert([
                'periode' => $periodeBaru,
                'tanggal' => now(),
                'tanggal_mulai' => $start,
                'tanggal_selesai' => $end,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ===============================
            // GENERATE SEMUA GROUP
            // ===============================
            $this->generateAssignments($periodeBaru, $start, $end);
        });
    }

    public function generateAssignments($periode, $start, $end)
    {
        // 🔥 ambil semua group unik
        $groupIds = User::whereNotNull('group_id')
            ->distinct()
            ->pluck('group_id');

        foreach ($groupIds as $groupId) {

            $users = User::where('group_id', $groupId)
                ->pluck('id')
                ->toArray();

            if (count($users) != 30) {
                Log::warning('Group tidak lengkap', [
                    'group_id' => $groupId,
                    'total_user' => count($users)
                ]);
                continue;
            }

            shuffle($users);

            for ($i = 1; $i <= 30; $i++) {
                JuzAssignment::create([
                    'group_id' => $groupId,
                    'user_id' => $users[$i - 1],
                    'juz_number' => $i,
                    'period_index' => $periode,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'active',
                ]);
            }

            Log::info('Generate juz', [
                'group_id' => $groupId,
                'periode' => $periode
            ]);
        }
    }
}
