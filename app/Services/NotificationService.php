<?php

namespace App\Services;

use App\Models\JuzAssignment;
use App\Models\HafalanLog;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    public function run()
    {
        $today = Carbon::today();

        // ambil assignment aktif
        $assignments = JuzAssignment::where('status', 'active')
            ->whereNotNull('user_id')
            ->get();

        foreach ($assignments as $assignment) {

            // =========================
            // 1. REMINDER HARIAN
            // =========================
            $hasTodayLog = HafalanLog::where('assignment_id', $assignment->id)
                ->whereDate('tanggal', $today)
                ->exists();

            if (!$hasTodayLog) {
                $this->createNotification(
                    $assignment->user_id,
                    'Reminder Hafalan',
                    'Hari ini belum setor hafalan'
                );
            }

            // =========================
            // 2. H-3 DAN H-1 DEADLINE (README 4.4)
            // =========================
            $sisaHari = $today->diffInDays(Carbon::parse($assignment->end_date)->startOfDay(), false);

            if ($sisaHari == 3) {
                $this->createNotification(
                    $assignment->user_id,
                    'Deadline H-3',
                    "Sisa 3 hari lagi untuk menyelesaikan hafalan Juz {$assignment->juz_number}"
                );
            }

            if ($sisaHari == 1) {
                $this->createNotification(
                    $assignment->user_id,
                    'Deadline H-1',
                    "Besok batas akhir hafalan Juz {$assignment->juz_number}"
                );
            }

            // =========================
            // 3. LATE
            // =========================
            if ($today->gt(Carbon::parse($assignment->end_date))) {

                // update status
                $assignment->update(['status' => 'late']);

                $this->createNotification(
                    $assignment->user_id,
                    'Terlambat',
                    'Anda belum menyelesaikan hafalan'
                );
            }
        }

        $this->notifAdminAnggotaTidakAktif();
    }

    /**
     * BRD FR-10: beri tahu admin grup jika ada anggota yang tidak lapor
     * >= 14 hari. Diulang paling sering sekali per 7 hari per anggota.
     */
    public function notifAdminAnggotaTidakAktif(): void
    {
        $keaktifan = app(KeaktifanService::class);

        $userIds = JuzAssignment::whereIn('status', ['active', 'late'])
            ->whereNotNull('user_id')
            ->where('end_date', '>=', Carbon::today()->subDays(KeaktifanService::BATAS_HARI))
            ->distinct()
            ->pluck('user_id');

        foreach (User::whereIn('id', $userIds)->get() as $anggota) {
            $hari = $keaktifan->hariTidakLapor($anggota->id);

            if ($hari === null || $hari < KeaktifanService::BATAS_HARI) {
                continue;
            }

            $admins = User::where('role', 'admin')
                ->where('group_id', $anggota->group_id)
                ->pluck('id');

            if ($admins->isEmpty()) {
                $admins = User::where('role', 'admin')->pluck('id');
            }

            $title = "Anggota tidak aktif: {$anggota->name}";

            foreach ($admins as $adminId) {
                $baruSaja = Notification::where('user_id', $adminId)
                    ->where('title', $title)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if ($baruSaja) continue;

                $this->createNotification(
                    $adminId,
                    $title,
                    "{$anggota->name} tidak lapor hafalan {$hari} hari. Juz-nya bisa diambil alih (badal).",
                    'warning'
                );
            }
        }
    }

    public function createNotification($userId, $title, $message, $type = 'reminder')
    {
        $exists = Notification::where('user_id', $userId)
            ->where('title', $title)
            ->whereDate('created_at', now())
            ->exists();

        if ($exists) return;

        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false
        ]);

        app(FcmService::class)->kirimKeUser($userId, $title, $message, ['type' => $type]);
    }
}
