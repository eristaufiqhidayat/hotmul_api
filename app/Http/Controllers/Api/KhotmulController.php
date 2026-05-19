<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Khotmulperiode; // Model untuk menyimpan info file
use App\Models\Khotmul;
use Illuminate\Support\Facades\DB;

class KhotmulController extends Controller
{
    /**
     * Tampilkan form upload
     */
    public function index(Request $request)
    {
         $lastPeriode = Khotmul::where('group_id', $request->group_id)
                       ->max('periode');// cari periode terakhir

        $query = Khotmul::query()
            ->leftJoin('daurah_anggota', 'daurah_anggota.user_id', '=', 'khotmul.anggota_id')
            ->select('khotmul.*', 'daurah_anggota.name')
            ->Where('khotmul.group_id', '=', $request->group_id)
            ->where('periode', operator: $lastPeriode);

        // Search filter
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('khotmul.id', 'like', '%' . $search . '%')
                    ->orWhere('khotmul.anggota_id', 'like', '%' . $search . '%');
            });
        }

        $data = $query->orderBy('khotmul.id', 'desc')->paginate(30);

        return response()->json($data);
    }
    public function indexAnggotaId($anggota_id, Request $request)
    {
        $lastPeriode = Khotmul::where('group_id', $request->group_id)
                       ->max('periode');// cari periode terakhir
        
        $data = Khotmul::query()
            ->leftJoin('daurah_anggota', 'daurah_anggota.user_id', '=', 'khotmul.anggota_id')
            ->select('khotmul.*', 'daurah_anggota.name')
            ->where('khotmul.anggota_id', $anggota_id)
            ->where('periode', $lastPeriode)
            ->orderBy('khotmul.id', 'desc')
            ->get(); // <- eksekusi query
 
        return response()->json(['data' => $data]);
    }
    public function create()
    {
        return view('upload'); // Blade view untuk form
    }

    /**
     * Proses upload file
     */
    public function store(Request $request)
    {

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // Simpan file ke storage/app/public/files
            $path = $file->store('files', 'public');

            // Simpan informasi file ke database
            $fileRecord = Khotmul::create([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            return response()->json(['message' => 'Data berhasil disimpan']);
        }
        //var_dump($request->headers->all());
        return response()->json(['message' => 'Tidak ada file yang diupload.']);
    }
    public function generateData(Request $request)
    {
        // $request->validate([
        //     'group_id' => 'required|integer',
        //     'periode' => 'required|integer',
        //     'tanggal' => 'required|date',
        //     'status' => 'required|string'
        // ]);

        $groupId = $request->group_id;
        $periode = $request->periode;
        $tanggal = $request->tanggal;
        $status = $request->status;

        // 1. Ambil semua anggota group
        $anggotaList = DB::table(DB::raw('`daurah_anggota`'))
            ->where('group_id', $groupId)
            ->pluck('user_id') // pastikan field-nya benar, kamu tulis user_id sebelumnya
            ->toArray();



        // 2. Siapkan array insert
        $data = [];
        for ($i = 1; $i <= 30; $i++) {
            $anggotaId = $anggotaList[$i - 1] ?? null;

            $data[] = [
                'group_id'   => $groupId,
                'juz'        => $i,
                'periode'    => $periode,
                'tanggal'    => $tanggal,
                'anggota_id' => $anggotaId,
                'status'     => $status,
            ];
        }

        // 3. Insert batch
        DB::table('khotmul')->insert($data);

        return response()->json([
            'success' => true,
            'inserted' => count($data),
            'data' => $data
        ]);
    }
    public function updateStatus(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            'status' => 'required|string|max:50',
        ]);

        // Cari data berdasarkan ID
        $khotmul = Khotmul::find($id);

        if (!$khotmul) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // Update status
        $khotmul->status = $request->status;
        $khotmul->save();

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diupdate',
            'data' => $khotmul
        ]);
    }
    public function getAnggotaKhotmulStats(Request $request)
    {
        try {
            $stats = DB::table('daurah_anggota as d')
                ->leftJoin('khotmul as k', 'd.user_id', '=', 'k.anggota_id')
                ->select(
                    'd.user_id',
                    'd.group_id',
                    'd.name',
                    DB::raw('COUNT(k.anggota_id) as jumlah_khotmul'),
                    DB::raw('GROUP_CONCAT(k.juz) as daftar_juz'),
                    DB::raw('GROUP_CONCAT(k.tanggal) as daftar_tanggal')
                )
                ->groupBy('d.user_id', 'd.group_id', 'd.name')
                ->orderByDesc('jumlah_khotmul')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik khotmul berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching khotmul stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAnggotaKhotmulStatsByGroup(Request $request, $group_id)
    {
        try {
            $stats = DB::table('daurah_anggota as d')
                ->leftJoin('khotmul as k', 'd.user_id', '=', 'k.anggota_id')
                ->select(
                    'd.user_id',
                    'd.group_id',
                    'd.name',
                    DB::raw('COUNT(k.anggota_id) as jumlah_khotmul'),
                    DB::raw('GROUP_CONCAT(k.juz) as daftar_juz'),
                    DB::raw('GROUP_CONCAT(k.tanggal) as daftar_tanggal')
                )
                ->where('d.group_id', $group_id)
                ->groupBy('d.user_id', 'd.group_id', 'd.name')
                ->orderByDesc('jumlah_khotmul')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik khotmul group ' . $group_id . ' berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching khotmul stats by group: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showAnggotaKhotmulStats()
    {
        try {
            $stats = DB::table('daurah_anggota as d')
                ->leftJoin('khotmul as k', 'd.user_id', '=', 'k.anggota_id')
                ->select(
                    'd.user_id',
                    'd.group_id',
                    'd.name',
                    DB::raw('COUNT(k.anggota_id) as jumlah_khotmul'),
                    DB::raw('GROUP_CONCAT(k.juz) as daftar_juz'),
                    DB::raw('GROUP_CONCAT(k.tanggal) as daftar_tanggal')
                )
                ->groupBy('d.user_id', 'd.group_id', 'd.name')
                ->orderByDesc('jumlah_khotmul')
                ->get();

            // Untuk view blade
            return view('khotmul.stats', compact('stats'));
        } catch (\Exception $e) {
            Log::error('Error showing khotmul stats: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menampilkan data statistik');
        }
    }
    public function getAnggotaKhotmulStatsByAnggotaid(Request $request)
    {
        try {
            $stats = DB::table('daurah_anggota')
                ->leftJoin('khotmul', function ($join) {
                    $join->on('khotmul.anggota_id', '=', 'daurah_anggota.user_id')
                        ->whereNotNull('khotmul.status')
                        ->where('khotmul.status', '<>', '');
                })
                ->select(
                    'daurah_anggota.user_id as anggota_id',
                    'daurah_anggota.name',
                    DB::raw('COUNT(khotmul.juz) as total_juz')
                )
                ->groupBy('daurah_anggota.user_id', 'daurah_anggota.name')
                ->orderByDesc('total_juz')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Data statistik khotmul berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching khotmul stats by group: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data statistik group',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
