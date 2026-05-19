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

                $start = now();
                $end = now()->copy()->addDays(6);

                DB::table('khotmul_periode')->insert([
                    'periode' => 1,
                    'tanggal' => now(),
                    'tanggal_mulai' => $start,
                    'tanggal_selesai' => $end,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
               
                Log::info('Periode pertama berhasil dibuat');

                return;
            }

            // =========================================
            // CEK APAKAH MASIH ACTIVE
            // =========================================
            $now = Carbon::now();

            $mulai = Carbon::parse($lastPeriode->tanggal_mulai);
            $selesai = Carbon::parse($lastPeriode->tanggal_selesai);

            $isActiveDate = $now->between($mulai, $selesai);

            // =========================================
            // JIKA MASIH DALAM RENTANG TANGGAL
            // =========================================
            if ($isActiveDate) {

                Log::info('Periode masih aktif', [
                    'periode' => $lastPeriode->periode,
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                ]);
                // echo "Periode masih aktif\n";
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

            $startBaru = $selesai->copy()->addDay();
            $endBaru = $startBaru->copy()->addDays(14);

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
            $this->newPeriode($lastPeriode->periode);
        });

        Log::info('RUN NEXT PERIODE END', [
            'time' => now()
        ]);
    }
    public function newPeriode($periodeBefore)
    {
        $periodes = DB::table('juz_assignments')->where('period_index', '=', $periodeBefore)
            ->orderBy('group_id', 'ASC')
            ->orderBy('juz_number', 'ASC')
            ->get();
        $updated = DB::table('juz_assignments')
            //->where('period_index', $periodeBefore)
            ->where('status', 'active')
            ->update([
                'status' => 'late',
                'updated_at' => now(),
            ]);

        dump("TOTAL UPDATED: " . $updated);
        $activeExists = DB::table('khotmul_periode')
            ->where('status', 'active')
            ->first();
        foreach ($periodes as $periode) {
            $juzBaru = $periode->juz_number == 30 ? 1 : $periode->juz_number + 1;
            echo $periode->group_id . '-' . $periode->user_id . '-' . $periode->juz_number . '-' . $juzBaru . '-' . $periode->period_index . "\n";
            JuzAssignment::create([
                'group_id' => $periode->group_id,
                'user_id' => $periode->user_id,
                'juz_number' => $juzBaru,
                'period_index' => $activeExists->periode,
                'start_date' => $activeExists->tanggal_mulai,
                'end_date' => $activeExists->tanggal_selesai,
                'status' => 'active',
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
