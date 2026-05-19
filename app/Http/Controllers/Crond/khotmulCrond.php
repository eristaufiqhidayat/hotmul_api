<?php

namespace App\Http\Controllers\Crond;

use App\Http\Controllers\Controller;
use App\Models\group;
use App\Models\JuzAssignment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class khotmulCrond extends Controller
{
    public function createPeriode()
    {
        Log::info('Cron khotmul START', ['time' => now()]);
        $this->distributeUsersToGroup();
        // ✅ ambil group_id dari users
        $groupIds = User::whereNotNull('group_id')
            ->distinct()
            ->pluck('group_id');

        foreach ($groupIds as $groupId) {

            Log::info('Process group', ['group_id' => $groupId]);

            $periode = DB::table('khotmul_periode')
                ->where('group_id', $groupId)
                ->orderByDesc('periode')
                ->first();

            if ($periode) {

                if (!empty($periode->tanggal_selesai) && now()->gt($periode->tanggal_selesai)) {

                    Log::info('Generate periode baru', [
                        'group_id' => $groupId,
                        'periode_lama' => $periode->periode
                    ]);

                    $this->createZus($groupId);
                } else {

                    Log::info('Belum waktunya generate', [
                        'group_id' => $groupId
                    ]);
                }
            } else {

                Log::info('Belum ada periode, generate pertama', [
                    'group_id' => $groupId
                ]);

                $this->createZus($groupId);
            }
        }

        $this->Sync();

        Log::info('Cron khotmul END', ['time' => now()]);

        return 'Selesai generate khotmul';
    }

    public function createZus($groupId)
    {
        DB::transaction(function () use ($groupId) {

            // ✅ ambil anggota dari users
            $anggota = User::where('group_id', $groupId)
                ->pluck('id')
                ->toArray();

            shuffle($anggota);

            $periodeTerakhir = DB::table('juz_assignments')
                ->where('group_id', $groupId)
                ->max('period_index');

            $periodeBaru = $periodeTerakhir ? $periodeTerakhir + 1 : 1;

            // ❗ Anti duplicate
            $exists = JuzAssignment::where('group_id', $groupId)
                ->where('period_index', $periodeBaru)
                ->exists();

            if ($exists) {
                Log::warning('Periode sudah ada, skip', [
                    'group_id' => $groupId,
                    'periode' => $periodeBaru
                ]);
                return;
            }

            for ($i = 1; $i <= 30; $i++) {

                $anggota_id = $i <= count($anggota) ? $anggota[$i - 1] : null;

                JuzAssignment::create([
                    'group_id'   => $groupId,
                    'juz_number'        => $i,
                    'period_index'    => $periodeBaru,
                    'start_date'    => now(),
                    'end_date'      => now()->addDays(7),
                    'user_id' => $anggota_id,
                    'status'     => 'active',
                ]);
            }

            Log::info('Berhasil create 30 juz', [
                'group_id' => $groupId,
                'periode' => $periodeBaru
            ]);
        });
    }

    public function cekDurasi($group_id)
    {
        return DB::table('khotmul_periode')
            ->where('group_id', $group_id)
            ->orderByDesc('periode')
            ->first(['periode', 'tanggal']);
    }

    public function Sync()
    {
        $data = DB::table('juz_assignments')
            ->select(
                'group_id',
                'period_index as periode',
                'start_date as tanggal'
            )
            ->groupBy('group_id', 'period_index', 'start_date')
            ->get();

        if ($data->isEmpty()) {
            Log::warning('Tidak ada data untuk sync');
            return;
        }

        DB::table('khotmul_periode')->truncate();

        DB::transaction(function () use ($data) {

            foreach ($data as $row) {
                DB::table('khotmul_periode')->insert([
                    'periode'   => $row->periode,
                    'group_id'  => $row->group_id,
                    'tanggal'   => $row->tanggal,
                ]);
            }
        });

        Log::info('Sync selesai', ['total' => count($data)]);

        return 'Sync selesai';
    }
    public function distributeUsersToGroup()
    {
        DB::transaction(function () {

            // ambil user tanpa group
            $users = User::whereNull('group_id')->get();

            if ($users->isEmpty()) {
                \Log::info('Tidak ada user tanpa group');
                return;
            }

            // ambil semua group yang sudah ada + hitung isinya
            $groups = User::select('group_id', DB::raw('count(*) as total'))
                ->whereNotNull('group_id')
                ->groupBy('group_id')
                ->orderBy('group_id')
                ->get()
                ->keyBy('group_id');

            // cari group terakhir
            $lastGroupId = $groups->keys()->max() ?? 0;

            foreach ($users as $user) {

                $assigned = false;

                // isi group yang belum penuh
                foreach ($groups as $groupId => $group) {

                    if ($group->total < 30) {

                        $user->update(['group_id' => $groupId]);

                        $groups[$groupId]->total++; // update counter
                        $assigned = true;

                        break;
                    }
                }

                // kalau semua group penuh → buat group baru
                if (!$assigned) {

                    $lastGroupId++;

                    $user->update(['group_id' => $lastGroupId]);

                    $groups[$lastGroupId] = (object)[
                        'group_id' => $lastGroupId,
                        'total' => 1
                    ];
                }
            }

            \Log::info('Distribusi group selesai');
        });
    }
    public function generateAssignments($periode)
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $users = User::where('group_id', $group->id)->get();

            if ($users->count() != 30) {
                Log::warning("Group {$group->id} tidak lengkap");
                continue;
            }

            foreach ($users as $index => $user) {
                JuzAssignment::create([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'juz_number' => $index + 1,
                    'periode_index' => $periode->periode,
                    'start_date' => $periode->tanggal_mulai,
                    'end_date' => $periode->tanggal_selesai,
                    'status' => 'active',
                ]);
            }
        }
    }
}
