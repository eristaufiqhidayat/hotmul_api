<?php

namespace App\Services;

use App\Models\BadalAssignment;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use Carbon\Carbon;

/**
 * Hitung berapa hari anggota tidak lapor hafalan (BRD FR-06 / FR-10).
 */
class KeaktifanService
{
    /** Anggota dianggap tidak aktif setelah 14 hari tanpa laporan. */
    public const BATAS_HARI = 14;

    /**
     * Hari sejak laporan terakhir user. Jika belum pernah lapor, dihitung
     * dari tanggal mulai juz pertamanya. Null jika belum punya juz.
     */
    public function hariTidakLapor($userId): ?int
    {
        $terakhir = HafalanLog::where('user_id', $userId)->max('tanggal');

        if (!$terakhir) {
            $terakhir = JuzAssignment::where('user_id', $userId)->min('start_date');
        }

        if (!$terakhir) {
            return null;
        }

        return (int) Carbon::parse($terakhir)->startOfDay()->diffInDays(Carbon::today());
    }

    /**
     * Juz yang boleh diambil alih: terlambat (late), atau masih aktif tetapi
     * pemiliknya tidak lapor >= 14 hari, dan belum ada badal yang berjalan
     * atau selesai.
     */
    public function bolehDibadal(JuzAssignment $assignment): bool
    {
        if (!in_array($assignment->status, ['active', 'late'])) {
            return false;
        }

        $sudahDibadal = BadalAssignment::where('assignment_id', $assignment->id)
            ->whereIn('status', ['active', 'done'])
            ->exists();

        if ($sudahDibadal) {
            return false;
        }

        if ($assignment->status === 'late') {
            return true;
        }

        return ($this->hariTidakLapor($assignment->user_id) ?? 0) >= self::BATAS_HARI;
    }
}
