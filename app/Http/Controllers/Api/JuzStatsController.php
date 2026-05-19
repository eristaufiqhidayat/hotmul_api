<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class JuzStatsController extends Controller
{
    /**
     * Get juz statistics with status filter
     */
    public function getJuzStatsWithStatus(Request $request)
    {
        try {
            // Validasi input jika ada parameter filter
            $validator = Validator::make($request->all(), [
                'group_id' => 'nullable|string',
                'min_juz' => 'nullable|integer|min:0',
                'max_juz' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                         ->whereNotNull('khotmul.status')
                         ->where('khotmul.status', '<>', '');
                })
                ->select(
                    'daurah_anggota.user_id as anggota_id',
                    'daurah_anggota.name',
                    'daurah_anggota.group_id',
                    DB::raw('COUNT(khotmul.juz) as total_juz')
                )
                ->groupBy('daurah_anggota.user_id', 'daurah_anggota.name', 'daurah_anggota.group_id')
                ->orderByDesc('total_juz');

            // Filter by group_id jika ada
            if ($request->has('group_id') && $request->group_id) {
                $query->where('daurah_anggota.group_id', $request->group_id);
            }

            $stats = $query->get();

            // Filter tambahan berdasarkan jumlah juz
            if ($request->has('min_juz')) {
                $stats = $stats->where('total_juz', '>=', $request->min_juz);
            }

            if ($request->has('max_juz')) {
                $stats = $stats->where('total_juz', '<=', $request->max_juz);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik juz dengan filter status berhasil diambil',
                'total_records' => $stats->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching juz stats with status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik juz',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get juz statistics by specific anggota
     */
    public function getJuzStatsByAnggota($anggota_id)
    {
        try {
            $stats = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                         ->whereNotNull('khotmul.status')
                         ->where('khotmul.status', '<>', '');
                })
                ->select(
                    'daurah_anggota.user_id as anggota_id',
                    'daurah_anggota.name',
                    'daurah_anggota.group_id',
                    DB::raw('COUNT(khotmul.juz) as total_juz'),
                    DB::raw('GROUP_CONCAT(DISTINCT khotmul.juz ORDER BY khotmul.juz ASC) as daftar_juz'),
                    DB::raw('GROUP_CONCAT(khotmul.tanggal ORDER BY khotmul.tanggal ASC) as tanggal_pembacaan'),
                    DB::raw('GROUP_CONCAT(khotmul.status) as status_list')
                )
                ->where('daurah_anggota.user_id', $anggota_id)
                ->groupBy('daurah_anggota.user_id', 'daurah_anggota.name', 'daurah_anggota.group_id')
                ->first();

            if (!$stats) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data anggota tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik juz anggota berhasil diambil'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching juz stats by anggota: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik juz anggota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get juz statistics with detailed information
     */
    public function getDetailedJuzStats(Request $request)
    {
        try {
            $query = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                         ->whereNotNull('khotmul.status')
                         ->where('khotmul.status', '<>', '');
                })
                ->select(
                    'daurah_anggota.user_id as anggota_id',
                    'daurah_anggota.name',
                    'daurah_anggota.group_id',
                    DB::raw('COUNT(khotmul.juz) as total_juz'),
                    DB::raw('GROUP_CONCAT(DISTINCT khotmul.juz ORDER BY khotmul.juz ASC) as daftar_juz'),
                    DB::raw('GROUP_CONCAT(khotmul.tanggal ORDER BY khotmul.tanggal ASC) as tanggal_pembacaan'),
                    DB::raw('MIN(khotmul.tanggal) as tanggal_mulai'),
                    DB::raw('MAX(khotmul.tanggal) as tanggal_terakhir'),
                    DB::raw('COUNT(DISTINCT khotmul.periode) as total_periode')
                )
                ->groupBy('daurah_anggota.user_id', 'daurah_anggota.name', 'daurah_anggota.group_id')
                ->orderByDesc('total_juz');

            // Filter by group_id jika ada
            if ($request->has('group_id') && $request->group_id) {
                $query->where('daurah_anggota.group_id', $request->group_id);
            }

            $stats = $query->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik juz detail berhasil diambil',
                'total_records' => $stats->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching detailed juz stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik juz detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get juz statistics summary
     */
    public function getJuzStatsSummary(Request $request)
    {
        try {
            $summary = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                         ->whereNotNull('khotmul.status')
                         ->where('khotmul.status', '<>', '');
                })
                ->select(
                    DB::raw('COUNT(DISTINCT daurah_anggota.user_id) as total_anggota'),
                    DB::raw('SUM(CASE WHEN khotmul.juz IS NOT NULL THEN 1 ELSE 0 END) as total_juz_dibaca'),
                    DB::raw('AVG(CASE WHEN khotmul.juz IS NOT NULL THEN 1 ELSE 0 END) * 100 as persentase_aktif'),
                    DB::raw('MAX(khotmul.tanggal) as tanggal_terakhir_aktivitas')
                )
                ->first();

            // Stats by group
            $groupStats = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                         ->whereNotNull('khotmul.status')
                         ->where('khotmul.status', '<>', '');
                })
                ->select(
                    'daurah_anggota.group_id',
                    DB::raw('COUNT(DISTINCT daurah_anggota.user_id) as total_anggota'),
                    DB::raw('COUNT(khotmul.juz) as total_juz_dibaca'),
                    DB::raw('ROUND(AVG(CASE WHEN khotmul.juz IS NOT NULL THEN 1 ELSE 0 END) * 100, 2) as persentase_aktif')
                )
                ->groupBy('daurah_anggota.group_id')
                ->orderByDesc('total_juz_dibaca')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'by_group' => $groupStats
                ],
                'message' => 'Data summary statistik juz berhasil diambil'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching juz stats summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data summary statistik juz',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}