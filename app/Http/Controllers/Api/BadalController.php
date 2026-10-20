<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BadalAssignment;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use App\Models\JuzReference;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\KeaktifanService;
use App\Services\KhotmulService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Badal / ambil alih juz anggota yang tidak aktif (BRD FR-06) dan
 * badge "Pengganti Aktif" (FR-07).
 */
class BadalController extends Controller
{
    public function __construct(private KeaktifanService $keaktifan)
    {
    }

    /**
     * GET /badal → juz yang bisa diambil alih di grup user, badal yang
     * sedang dikerjakan user, dan jumlah badge Pengganti Aktif.
     */
    public function index(Request $request)
    {
        $me = $request->user();
        $groupId = $me->role === 'admin' && $request->filled('group_id')
            ? (int) $request->group_id
            : (int) $me->group_id;

        $kandidat = [];

        if ($groupId) {
            // hanya periode berjalan dan satu periode sebelumnya, dipilih
            // dari tanggal (bukan max period_index) supaya data sisa bug
            // cron lama yang bertanggal jauh ke depan tidak ikut terbaca
            $hariIni = Carbon::today();

            $assignments = JuzAssignment::where('group_id', $groupId)
                ->whereIn('status', ['active', 'late'])
                ->whereDate('start_date', '<=', $hariIni)
                ->whereDate('end_date', '>=', $hariIni->copy()->subDays(KhotmulService::HARI_PER_PERIODE))
                ->where('user_id', '!=', $me->id)
                ->orderBy('juz_number')
                ->get();

            $nama = User::whereIn('id', $assignments->pluck('user_id'))->pluck('name', 'id');

            foreach ($assignments as $a) {
                if (!$this->keaktifan->bolehDibadal($a)) {
                    continue;
                }

                $kandidat[] = [
                    'assignment_id' => $a->id,
                    'user_id' => $a->user_id,
                    'name' => $nama[$a->user_id] ?? '-',
                    'juz' => $a->juz_number,
                    'period_index' => $a->period_index,
                    'end_date' => $a->end_date,
                    'status' => $a->status,
                    'hari_tidak_lapor' => $this->keaktifan->hariTidakLapor($a->user_id),
                    'progres' => $this->progres($a),
                ];
            }
        }

        return response()->json([
            'group_id' => $groupId ?: null,
            'kandidat' => $kandidat,
            'badal_saya' => $this->badalSaya($me->id),
            'badge' => $this->ringkasanBadge($me->id),
        ]);
    }

    /**
     * POST /badal/{assignment}/ambil
     */
    public function ambil(Request $request, $assignmentId)
    {
        $me = $request->user();
        $assignment = JuzAssignment::findOrFail($assignmentId);

        if ((int) $assignment->user_id === (int) $me->id) {
            return response()->json(['message' => 'Tidak bisa mengambil alih juz sendiri'], 422);
        }

        if ($me->role !== 'admin' && (int) $assignment->group_id !== (int) $me->group_id) {
            return response()->json(['message' => 'Juz ini bukan milik grup Anda'], 403);
        }

        // satu badal berjalan per anggota, supaya target tidak menumpuk
        $masihBadal = BadalAssignment::where('pengganti_user_id', $me->id)
            ->where('status', 'active')
            ->exists();

        if ($masihBadal) {
            return response()->json(['message' => 'Selesaikan dulu juz badal yang sedang berjalan'], 422);
        }

        $badal = DB::transaction(function () use ($assignment, $me) {
            // kunci baris supaya dua orang tidak mengambil juz yang sama
            $assignment = JuzAssignment::whereKey($assignment->id)->lockForUpdate()->first();

            if (!$this->keaktifan->bolehDibadal($assignment)) {
                return null;
            }

            $badal = BadalAssignment::create([
                'assignment_id' => $assignment->id,
                'original_user_id' => $assignment->user_id,
                'pengganti_user_id' => $me->id,
                'group_id' => $assignment->group_id,
                'juz_number' => $assignment->juz_number,
                'period_index' => $assignment->period_index,
                'status' => 'active',
            ]);

            UserBadge::create([
                'user_id' => $me->id,
                'badge' => UserBadge::PENGGANTI_AKTIF,
                'keterangan' => "Mengambil alih Juz {$assignment->juz_number}",
                'badal_id' => $badal->id,
            ]);

            return $badal;
        });

        if (!$badal) {
            return response()->json(['message' => 'Juz ini tidak bisa diambil alih (sudah ada pengganti atau anggota masih aktif)'], 422);
        }

        $this->beritahuGrup($badal, $me);

        return response()->json([
            'message' => "Anda menggantikan Juz {$badal->juz_number}",
            'data' => $badal,
            'badge' => $this->ringkasanBadge($me->id),
        ], 201);
    }

    /**
     * POST /badal/{id}/batal → pengganti atau admin membatalkan badal.
     */
    public function batal(Request $request, $id)
    {
        $me = $request->user();
        $badal = BadalAssignment::where('status', 'active')->findOrFail($id);

        if ($me->role !== 'admin' && (int) $badal->pengganti_user_id !== (int) $me->id) {
            return response()->json(['message' => 'Bukan badal Anda'], 403);
        }

        DB::transaction(function () use ($badal) {
            $badal->update(['status' => 'cancelled']);
            UserBadge::where('badal_id', $badal->id)->delete();
        });

        return response()->json(['message' => 'Badal dibatalkan']);
    }

    /**
     * GET /badges → badge milik user login.
     */
    public function badges(Request $request)
    {
        return response()->json($this->ringkasanBadge($request->user()->id));
    }

    private function badalSaya($userId): array
    {
        return BadalAssignment::where('pengganti_user_id', $userId)
            ->whereIn('status', ['active', 'done'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($b) {
                $a = JuzAssignment::find($b->assignment_id);

                return [
                    'id' => $b->id,
                    'assignment_id' => $b->assignment_id,
                    'juz' => $b->juz_number,
                    'period_index' => $b->period_index,
                    'status' => $b->status,
                    'original_name' => User::where('id', $b->original_user_id)->value('name'),
                    'end_date' => optional($a)->end_date,
                    'progres' => $a ? $this->progres($a) : 0,
                ];
            })
            ->all();
    }

    private function ringkasanBadge($userId): array
    {
        $jumlah = UserBadge::where('user_id', $userId)
            ->where('badge', UserBadge::PENGGANTI_AKTIF)
            ->count();

        return [
            'pengganti_aktif' => $jumlah,
            'label' => $jumlah > 0 ? "Pengganti Aktif ×{$jumlah}" : null,
        ];
    }

    /** Persentase ayat yang sudah disetor untuk satu juz (0–100). */
    private function progres(JuzAssignment $a): int
    {
        $target = (int) (JuzReference::where('juz_number', $a->juz_number)->value('total_ayat') ?? 0);
        if ($target <= 0) {
            return 0;
        }

        $ranges = HafalanLog::where('assignment_id', $a->id)
            ->whereNotNull('ayat_from')->whereNotNull('ayat_to')
            ->orderBy('ayat_from')->get(['ayat_from', 'ayat_to']);

        $total = 0;
        $from = $to = null;
        foreach ($ranges as $r) {
            if ($to === null || $r->ayat_from > $to + 1) {
                if ($to !== null) $total += $to - $from + 1;
                [$from, $to] = [$r->ayat_from, $r->ayat_to];
            } else {
                $to = max($to, $r->ayat_to);
            }
        }
        if ($to !== null) $total += $to - $from + 1;

        return (int) min(100, round($total * 100 / $target));
    }

    private function beritahuGrup(BadalAssignment $badal, User $pengganti): void
    {
        $notif = app(NotificationService::class);

        $notif->createNotification(
            $badal->original_user_id,
            "Juz {$badal->juz_number} diambil alih",
            "{$pengganti->name} menggantikan Juz {$badal->juz_number} Anda periode ini.",
            'warning'
        );

        $anggota = User::where('group_id', $badal->group_id)
            ->whereNotIn('id', [$pengganti->id, $badal->original_user_id])
            ->pluck('id');

        foreach ($anggota as $userId) {
            $notif->createNotification(
                $userId,
                "{$pengganti->name} menjadi Pengganti Aktif",
                "{$pengganti->name} mengambil alih Juz {$badal->juz_number}. Barakallahu fiik!",
                'done'
            );
        }
    }
}
