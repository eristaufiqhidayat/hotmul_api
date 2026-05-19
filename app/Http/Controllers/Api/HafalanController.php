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

        // ❌ CEK KEPEMILIKAN
        if ($assignment->user_id != auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized assignment'
            ], 403);
        }

        // ❌ CEK STATUS
        if ($assignment->status != 'active') {
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

            // 🔥 HITUNG TOTAL AYAT
            $total = HafalanLog::where('assignment_id', $assignment->id)
                ->sum(DB::raw('(ayat_to - ayat_from + 1)'));

            // 🔥 OPTIONAL: AUTO DONE (kalau sudah selesai)
            // misalnya target 200 ayat (ambil dari tabel lain kalau ada)
            $targetAyat = 200;

            //if ($total >= $targetAyat) {
            $assignment->status = 'done';
            $assignment->save();
            //}

            DB::commit();

            return response()->json([
                'message' => 'Setoran berhasil',
                'data' => $log,
                'total_ayat' => $total,
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
    public function today()
    {
        $user = auth()->user();
        Log::info('MASUK TODAY', ['user_id' => $user->id]);
        $group = \App\Models\Group::find($user->group_id);
        $khotmul_periode = \App\Models\Khotmulperiode::where('status', 'active')
            ->first();
        $periode = $group->periode + $khotmul_periode->periode;
        Log::info('GROUP CHECK', ['group' => $group]);
        if (!$group) {
            return response()->json(['data' => []]);
        }
        Log::info('User group', ['group_id' => $group->id, 'group_name' => $group->name]);
        $members = User::where('group_id', $group->id)->get();
        Log::info('Group members', ['count' => $members->count()]);

        $data = $members->map(function ($user) use ($group, $khotmul_periode) {
            Log::info('Processing user', ['user_id' => $user->id, 'user_name' => $user->name]);

            $assignment = \App\Models\JuzAssignment::where('user_id', $user->id)
                ->where('period_index', $khotmul_periode->periode)
                ->whereIn('status', ['active', 'done'])
                ->first();

            $log = \App\Models\HafalanLog::where('user_id', $user->id)
                ->whereDate('tanggal', now())
                ->first();

            $totalAyat = 0;
            $targetAyat = 0;

            if ($assignment) {
                // ✅ ambil log TERAKHIR (bukan sum)
                $lastLog = \App\Models\HafalanLog::where('assignment_id', $assignment->id)
                    ->orderBy('tanggal', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastLog) {
                    $totalAyat = ($lastLog->ayat_to - $lastLog->ayat_from + 1);
                }

                $targetAyat = \App\Models\JuzReference::where('juz_number', $assignment->juz_number)
                    ->value('total_ayat') ?? 0;
            }
            Log::info('Assignment', [$assignment]);
            $member = GroupMember::where('user_id', $user->id)
                ->where('group_id', $group->id)
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

                'total_ayat' => (int) $totalAyat,   // 🔥 FIX
                'target_ayat' => (int) $targetAyat, // 🔥 FIX
                'periode_index' => optional($assignment)->period_index,
            ];
        });
        return response()->json([
            'group_id' => $group->id,
            'group_name' => $group->name,
            'periode' => $periode,
            'data' => $data
        ]);
        //return response()->json(['data' => $data]);
    }
}
