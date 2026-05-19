<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Khotmulperiode; // Model untuk menyimpan info file
use App\Models\Khotmul;
use App\Models\Anggota;
// Model untuk menyimpan info file
use Illuminate\Support\Facades\DB;

class KhotmulPeriodeController extends Controller
{
    /**
     * Tampilkan form upload
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('khotmul_periode')
                ->select('*');

            // 🔄 Order by dan paginate - TANPA get() sebelumnya
            $data = $query->orderBy('tanggal', 'desc')
                ->paginate(10);

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createPeriode(Request $request)
    {

        $validated = $request->validate([
            'group_id' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            // Hapus data lama
            // Khotmul::where('group_id', $validated['group_id'])
            //     ->delete();

            // Ambil anggota group ini
            $anggota = Anggota::where('group_id', $validated['group_id'])
                ->pluck('user_id') // ambil id anggota
                ->toArray();

            // Acak urutan anggota
            shuffle($anggota);
            $periodeTerakhir = DB::table('khotmul')
                ->where('group_id', $validated['group_id'])
                ->max('periode');

            $periodeBaru = $periodeTerakhir ? $periodeTerakhir + 1 : 1;
            // Insert 30 juz
            for ($i = 1; $i <= 30; $i++) {
                $anggota_id = null;

                // Kalau masih ada anggota yang bisa di-assign
                if ($i <= count($anggota)) {
                    $anggota_id = $anggota[$i - 1]; // assign 1 anggota per juz
                }

                Khotmul::create([
                    'group_id'   => $validated['group_id'],
                    'juz'        => $i,
                    'periode'    => $periodeBaru,
                    'tanggal'    => date('Y-m-d H:i:s'),
                    'anggota_id' => $anggota_id,
                    'status'     => 'belum',
                ]);
            }
        });
        $this->Sync();
        return response()->json(['message' => '30 juz berhasil dibuat & dibagi ke anggota']);
    }
    public function store(Request $request)
    {
        try {

            $request->validate([
                'periode' => 'required',
                'tanggal_mulai' => 'required',
                'tanggal_selesai' => 'required',
                'status' => 'required',
            ]);

            $id = DB::table('khotmul_periode')->insertGetId([
                'periode' => $request->periode,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'tanggal' => now(),
                'status' => $request->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditambah',
                'id' => $id,
            ]);
        } catch (\Exception $e) {

            //Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Proses upload file
     */

    public function Sync()
    {

        //Mencari data periode terakhir 
        $data = DB::table('khotmul')
            ->select(
                'group_id',
                DB::raw('MAX(periode) as periode'),
                DB::raw('MAX(tanggal) as tanggal')
            )
            ->groupBy('group_id')
            ->orderBy('group_id', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data khotmul dengan parameter tersebut '
            ], 404);
        }
        DB::table('khotmul_periode')->truncate();
        foreach ($data as $row) {
            DB::table('khotmul_periode')->insert([
                'periode'   => $row->periode,
                'group_id'  => $row->group_id,
                'tanggal'   => $row->tanggal,
                'keterangan' => 'Generated from khotmul',
            ]);
        }

        return response()->json([
            'message' => 'Data berhasil disimpan ke khotmul_periode',
            'count'   => count($data),
        ]);
    }
    public function update(Request $request, $id)
    {
        try {

            $request->validate([
                'periode' => 'required',
                'tanggal_mulai' => 'required',
                'tanggal_selesai' => 'required',
                'status' => 'required',
            ]);

            DB::table('khotmul_periode')
                ->where('id', $id)
                ->update([
                    'periode' => $request->periode,
                    'tanggal_mulai' => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                    'status' => $request->status,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diupdate',
            ]);
        } catch (\Exception $e) {

            //Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy($id)
    {
        try {

            DB::table('khotmul_periode')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus',
            ]);
        } catch (\Exception $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function show()
    {
        try {

            $data = DB::table('khotmul_periode')
                ->where('status', 'active')
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            //Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function last()
    {
        try {

            $data = DB::table('khotmul_periode')
                ->orderByDesc('periode')
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {

            //Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
