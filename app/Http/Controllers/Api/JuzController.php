<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\juzAlquranUrutAyah;
use Illuminate\Http\Request;

class JuzController extends Controller
{
    /**
     * Menampilkan data juz dengan jumlah ayat berdasarkan parameter juz
     */
    public function getJuzByNumber(Request $request, $juz = null)
    {
        // Jika juz tidak diberikan sebagai parameter, cek dari query string
        if ($juz === null) {
            $juz = $request->query('juz');
        }

        // Validasi parameter juz
        if ($juz === null) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz diperlukan'
            ], 400);
        }

        // Validasi numeric
        if (!is_numeric($juz)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz harus berupa angka'
            ], 400);
        }

        // Validasi range juz (1-30)
        $juz = (int)$juz;
        if ($juz < 1 || $juz > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz harus antara 1 sampai 30'
            ], 400);
        }

        $data = juzAlquranUrutAyah::selectRaw('
                id,
                juz,
                noSurah_awal,
                noAyah_awal,
                noSurah_akhir,
                noAyah_akhir,
                (globalAkhir - globalAwal) AS jumlah_ayat
            ')
            ->where('juz', $juz)
            ->orderBy('id')
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data juz ' . $juz . ' tidak ditemukan'
            ], 404);
        }

        // Hitung total ayat untuk juz tersebut
        $totalAyat = $data->sum('jumlah_ayat');

        return response()->json([
            'success' => true,
            'juz' => $juz,
            'data' => $data,
            'total_ayat' => $totalAyat,
            'jumlah_range' => $data->count(),
            'message' => 'Data juz ' . $juz . ' berhasil diambil'
        ]);
    }

    /**
     * Menampilkan data semua juz atau filter by multiple juz
     */
    public function getAllJuz(Request $request)
    {
        $juzList = $request->query('juz');
        
        $query = juzAlquranUrutAyah::selectRaw('
                id,
                juz,
                noSurah_awal,
                noAyah_awal,
                noSurah_akhir,
                noAyah_akhir,
                (noAyah_akhir - noAyah_awal) AS jumlah_ayat
            ')
            ->orderBy('juz')
            ->orderBy('id');

        // Filter by multiple juz (comma separated)
        if ($juzList !== null) {
            $juzArray = explode(',', $juzList);
            $juzArray = array_map('intval', $juzArray);
            $juzArray = array_filter($juzArray, function($juz) {
                return $juz >= 1 && $juz <= 30;
            });
            
            if (!empty($juzArray)) {
                $query->whereIn('juz', $juzArray);
            }
        }

        $data = $query->get();

        // Group by juz dan hitung summary
        $groupedData = $data->groupBy('juz')->map(function($items, $juz) {
            return [
                'juz' => (int)$juz,
                'data' => $items,
                'total_ayat' => $items->sum('jumlah_ayat'),
                'jumlah_range' => $items->count()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $groupedData,
            'total_juz' => $groupedData->count(),
            'message' => 'Data juz berhasil diambil'
        ]);
    }

    /**
     * Menampilkan data dengan perhitungan akurat berdasarkan parameter juz
     */
    public function getAccurateJuzByNumber(Request $request, $juz = null)
    {
        if ($juz === null) {
            $juz = $request->query('juz');
        }

        if ($juz === null) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz diperlukan'
            ], 400);
        }

        if (!is_numeric($juz)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz harus berupa angka'
            ], 400);
        }

        $juz = (int)$juz;
        if ($juz < 1 || $juz > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter juz harus antara 1 sampai 30'
            ], 400);
        }

        $data = juzAlquranUrutAyah::selectRaw('
                id,
                juz,
                noSurah_awal,
                noAyah_awal,
                noSurah_akhir,
                noAyah_akhir,
                CASE 
                    WHEN noSurah_awal = noSurah_akhir THEN 
                        (noAyah_akhir - noAyah_awal) + 1
                    ELSE 
                        (noAyah_akhir - noAyah_awal)
                END AS jumlah_ayat
            ')
            ->where('juz', $juz)
            ->orderBy('id')
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data juz ' . $juz . ' tidak ditemukan'
            ], 404);
        }

        $totalAyat = $data->sum('jumlah_ayat');

        return response()->json([
            'success' => true,
            'juz' => $juz,
            'data' => $data,
            'total_ayat' => $totalAyat,
            'jumlah_range' => $data->count(),
            'message' => 'Data juz ' . $juz . ' dengan perhitungan akurat berhasil diambil'
        ]);
    }
}