<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DonasiController extends Controller
{
    // List semua daurah
    public function index(Request $request)
    {
        try {
            \DB::enableQueryLog();
            $data = Donasi::leftJoin('users', 'donasi.user_id', '=', 'users.id')
                ->select('donasi.*', 'users.name')
                ->paginate(10);
            \Log::info(\DB::getQueryLog());
            Log::info('Data donasi berhasil diambil', ['count' => $data->count()]);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error ambil donasi', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Simpan daurah baru
    public function store(Request $request)
    {
        $data = $request->all();

        $donasi = Donasi::create($data);

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $donasi
        ]);
    }

    // Tampilkan 1 daurah
    public function show($id)
    {
        $daurah = Khatam::find($id);
        if (!$daurah) {
            return response()->json(['message' => 'Daurah not found'], 404);
        }
        return response()->json($daurah);
    }

    // Update daurah
    public function update(Request $request, $id)
    {
        // Validasi data yang masuk
        $validated = $request->validate([
            'user_id'             => 'required|integer',
            'tanggal'       => 'required',
            'rp'   => 'required',
        ]);

        // Cari data berdasarkan ID
        $donasi = Donasi::findOrFail($id);
        // Update data
        $donasi->update($validated);

        return response()->json([
            'message' => 'Data berhasil diupdate',
            'data'    => $donasi
        ]);
    }


    // Hapus daurah
    public function destroy($id)
    {
        $donasi = Donasi::find($id);
        if (!$donasi) {
            return response()->json(['message' => 'Donasi not found'], 404);
        }

        $donasi->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
}
