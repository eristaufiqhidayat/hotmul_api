<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    // List semua daurah
public function index(Request $request)
{
    $query = Jadwal::query();

    // Search filter (contoh: cari berdasarkan group_name atau keterangan)
    if ($request->has('search') && $request->search != '') {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('jadwal_khatam.nama_cara', 'like', '%' . $search . '%')
              ->orWhere('jadwal_khatam.nama_tempat', 'like', '%' . $search . '%');
        });
    }

    $data = $query->orderBy('jadwal_khatam.id', 'desc')->paginate(7);

    return response()->json($data);
}


    // Simpan daurah baru
public function store(Request $request)
{
    $data = $request->all();

    $donasi = Jadwal::create($data);

    return response()->json([
        'message' => 'Data berhasil disimpan',
        'data' => $donasi
    ]);
}

    // Tampilkan 1 daurah
    public function show($id)
    {
        $daurah = Jadwal::find($id);
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
        'nama_acara' => 'required',
        'nama_tempat' => 'required',
        'waktu' => 'required',
        'alamat' => 'required',
    ]);

    // Cari data berdasarkan ID
    $donasi = Jadwal::findOrFail($id);
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
        $donasi = Jadwal::find($id);
        if (!$donasi) {
            return response()->json(['message' => 'Donasi not found'], 404);
        }

        $donasi->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
}
