<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanHafalanController extends Controller
{
    public function laporanByDaurah(
        $id,
        Request $request
    ) {

        try {

            $periode = $request->periode;

            /// jika request kosong ttt
            if (!$periode) {

                $periodeActive = DB::table('khotmul_periode')
                    ->where('status', 'active')
                    ->first();

                $periode = $periodeActive->periode;
            }

            $data = DB::table('juz_assignments')
                ->join(
                    'users',
                    'juz_assignments.user_id',
                    '=',
                    'users.id'
                )
                ->where(
                    'juz_assignments.group_id',
                    $id
                )
                ->where(
                    'juz_assignments.period_index',
                    $periode
                )
                ->select(
                    'users.name',
                    'juz_assignments.id',
                    'juz_assignments.juz_number',
                    'juz_assignments.status',
                    'juz_assignments.start_date',
                    'juz_assignments.end_date',
                    'juz_assignments.period_index'
                )
                ->orderBy('users.name')
                ->orderBy('juz_assignments.juz_number')
                ->get()
                ->map(function ($item) {

                    $progress = 0;

                    if ($item->status == 'done') {
                        $progress = 100;
                    } elseif ($item->status == 'active') {
                        $progress = 0;
                    } elseif ($item->status == 'late') {
                        $progress = 20;
                    }

                    return [
                        'name' => $item->name,
                        'assignment_id' => $item->id,
                        'juz' => $item->juz_number,
                        'progress' => $progress,
                        'status' => $item->status,
                        'last_input' => $item->end_date,
                    ];
                });

            return response()->json([
                'success' => true,
                'periode' => $periode,
                'data' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
