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
            $query = Donasi::leftJoin('users', 'donasi.user_id', '=', 'users.id')
                ->select('donasi.*', 'users.name');

            // anggota hanya melihat donasinya sendiri
            if (auth()->user()->role !== 'admin') {
                $query->where('donasi.user_id', auth()->id());
            }

            $data = $query->orderByDesc('donasi.tanggal')->paginate(10);
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
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'tanggal' => 'required|date',
            'rp'      => 'required|numeric|min:1',
        ]);

        // anggota hanya boleh mencatat donasi atas namanya sendiri
        if (auth()->user()->role !== 'admin' || empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        $donasi = Donasi::create($data);

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $donasi
        ]);
    }

    // Tampilkan 1 daurah
    public function show($id)
    {
        $donasi = Donasi::find($id);
        if (!$donasi || (auth()->user()->role !== 'admin' && $donasi->user_id != auth()->id())) {
            return response()->json(['message' => 'Donasi not found'], 404);
        }
        return response()->json($donasi);
    }

    // Update daurah
    public function update(Request $request, $id)
    {
        // Validasi data yang masuk
        $validated = $request->validate([
            'user_id'             => 'required|integer',
            'tanggal'       => 'required|date',
            'rp'   => 'required|numeric|min:1',
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
