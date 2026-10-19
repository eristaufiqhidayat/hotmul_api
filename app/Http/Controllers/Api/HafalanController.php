<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use Illuminate\Support\Facades\Log;
use App\Models\JuzReference;
use Illuminate\Support\Facades\DB;

use App\Models\GroupMember;
use App\Models\User;

class HafalanController extends Controller
{
    public function store(Request $request)
    {
        // ✅ VALIDASI
        $request->validate([
            'assignment_id' => 'required|exists:juz_assignments,id',
            'ayat_from' => 'required|integer|min:1',
            'ayat_to' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        // ✅ AMBIL ASSIGNMENT
        $assignment = JuzAssignment::find($request->assignment_id);

        // badal yang sedang berjalan untuk juz ini (BRD FR-06)
        $badal = \App\Models\BadalAssignment::where('assignment_id', $assignment->id)
            ->where('status', 'active')
            ->first();
        $isPengganti = $badal && $badal->pengganti_user_id == auth()->id();

        // ❌ CEK KEPEMILIKAN (pemilik juz atau penggantinya)
        if ($assignment->user_id != auth()->id() && !$isPengganti) {
            return response()->json([
                'message' => 'Unauthorized assignment'
            ], 403);
        }

        // ❌ CEK STATUS (pengganti boleh melanjutkan juz yang terlambat)
        if ($assignment->status != 'active' && !($isPengganti && $assignment->status == 'late')) {
            return response()->json([
                'message' => 'Assignment sudah tidak aktif'
            ], 400);
        }

        // ❌ VALIDASI RANGE AYAT
        if ($request->ayat_from > $request->ayat_to) {
            return response()->json([
                'message' => 'Ayat tidak valid'
            ], 422);
        }

        // target = jumlah ayat juz ini (0 jika referensi belum diisi)
        $targetAyat = (int) (JuzReference::where('juz_number', $assignment->juz_number)
            ->value('total_ayat') ?? 0);

        if ($targetAyat > 0 && $request->ayat_to > $targetAyat) {
            return response()->json([
                'message' => "Ayat akhir maksimal {$targetAyat}"
            ], 422);
        }

        DB::beginTransaction();

        try {

            // ✅ SIMPAN LOG
            $log = HafalanLog::updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'user_id' => auth()->id(),
                    'tanggal' => now()->toDateString(),
                ],
                [
                    'ayat_from' => $request->ayat_from,
                    'ayat_to' => $request->ayat_to,
                    'keterangan' => $request->keterangan,
                    'status' => 'progress',
                ]
            );

            // 🔥 HITUNG AYAT YANG SUDAH DISETOR (tanpa dobel hitung)
            $total = $this->ayatTercapai($assignment->id);

            // ✅ SELESAI hanya jika seluruh ayat juz sudah disetor
            if ($targetAyat > 0 && $total >= $targetAyat) {
                $assignment->status = 'done';
                $assignment->save();

                $log->status = 'selesai';
                $log->save();

                if ($badal) {
                    $badal->update(['status' => 'done', 'selesai_at' => now()]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Setoran berhasil',
                'data' => $log,
                'total_ayat' => $total,
                'target_ayat' => $targetAyat,
                'status_assignment' => $assignment->status
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Jumlah ayat berbeda yang sudah disetor untuk satu assignment
     * (gabungan semua rentang ayat_from–ayat_to, irisan dihitung sekali).
     */
    private function ayatTercapai($assignmentId): int
    {
        $ranges = HafalanLog::where('assignment_id', $assignmentId)
            ->whereNotNull('ayat_from')
            ->whereNotNull('ayat_to')
            ->orderBy('ayat_from')
            ->get(['ayat_from', 'ayat_to']);

        $total = 0;
        $from = null;
        $to = null;

        foreach ($ranges as $range) {
            if ($to === null || $range->ayat_from > $to + 1) {
                if ($to !== null) {
                    $total += $to - $from + 1;
                }
                $from = $range->ayat_from;
                $to = $range->ayat_to;
            } else {
                $to = max($to, $range->ayat_to);
            }
        }

        if ($to !== null) {
            $total += $to - $from + 1;
        }

        return $total;
    }

    public function today()
    {
        $user = auth()->user();

        // ❗ user belum dimasukkan admin ke grup
        if (empty($user->group_id)) {
            return response()->json([
                'group_id' => null,
                'group_name' => null,
                'periode' => null,
                'message' => 'Akun belum dimasukkan ke grup',
                'data' => []
            ]);
        }

        $group = \App\Models\Group::find($user->group_id);
        $groupId = (int) $user->group_id;
        $groupName = $group->name ?? "Grup {$groupId}";

        $khotmul_periode = \App\Models\Khotmulperiode::where('status', 'active')
            ->first();

        // ❗ admin belum membuat periode aktif
        if (!$khotmul_periode) {
            return response()->json([
                'group_id' => $groupId,
                'group_name' => $groupName,
                'periode' => null,
                'message' => 'Belum ada periode aktif',
                'data' => []
            ]);
        }

        $periode = ($group->periode ?? 0) + $khotmul_periode->periode;

        $members = User::where('group_id', $groupId)->get();
        Log::info('Group members', ['group_id' => $groupId, 'count' => $members->count()]);

        $data = $members->map(function ($user) use ($groupId, $khotmul_periode) {

            $assignment = \App\Models\JuzAssignment::where('user_id', $user->id)
                ->where('period_index', $khotmul_periode->periode)
                ->whereIn('status', ['active', 'done', 'late'])
                ->first();

            $log = \App\Models\HafalanLog::where('user_id', $user->id)
                ->whereDate('tanggal', now())
                ->first();

            $totalAyat = 0;
            $targetAyat = 0;

            if ($assignment) {
                $totalAyat = $this->ayatTercapai($assignment->id);

                $targetAyat = \App\Models\JuzReference::where('juz_number', $assignment->juz_number)
                    ->value('total_ayat') ?? 0;
            }
            $member = GroupMember::where('user_id', $user->id)
                ->where('group_id', $groupId)
                ->first();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'juz' => optional($assignment)->juz_number,

                // 🔥 dari pivot
                'order_number' => optional($member)->order_number,
                'juz_start' => optional($member)->juz_start,
                'status_assignment' => $assignment->status ?? 'none',
                'sudah_setor' => $log ? true : false,
                'ayat_from' => optional($log)->ayat_from,
                'ayat_to' => optional($log)->ayat_to,

                'total_ayat' => (int) $totalAyat,
                'target_ayat' => (int) $targetAyat,
                'periode_index' => optional($assignment)->period_index,
            ];
        });
        return response()->json([
            'group_id' => $groupId,
            'group_name' => $groupName,
            'periode' => $periode,
            'data' => $data
        ]);
    }
}
