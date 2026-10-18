<?php

namespace App\Services;

use App\Models\JuzAssignment;
use App\Models\HafalanLog;
use App\Models\Notification;
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
    }

    private function createNotification($userId, $title, $message)
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
            'type' => 'reminder',
            'is_read' => false
        ]);
    }
}
