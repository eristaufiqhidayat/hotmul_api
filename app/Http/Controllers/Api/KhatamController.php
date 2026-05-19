<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Khatam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class KhatamController extends Controller
{
    // List semua daurah
    public function index(Request $request)
    {
        // $query = Khatam::query()
        //     ->join('daurah_anggota', 'khatam.user_id', '=', 'daurah_anggota.user_id')
        //     ->select(
        //         'khatam.id',
        //         'khatam.user_id',
        //         'khatam.jumlah_khatam',
        //         'khatam.jumlah_tidak_baca',
        //         'khatam.jumlah_membadalkan',
        //         'khatam.keterangan',
        //         'khatam.tanggal',
        //         'daurah_anggota.group_id',
        //         'daurah_anggota.name as group_name'
        //     );

        // if ($request->has('search') && $request->search != '') {
        //     $search = $request->search;
        //     $query->where(function ($q) use ($search) {
        //         $q->where('khatam.keterangan', 'like', '%' . $search . '%')
        //             ->orWhere('daurah_anggota.name', 'like', '%' . $search . '%');
        //     });
        // }
        $query = DB::table('khotmul')
            ->join('daurah_anggota', 'khotmul.anggota_id', '=', 'daurah_anggota.user_id')
            ->where('status', 'send_voice');
         
        // Search filter (contoh: cari berdasarkan group_name atau keterangan)
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('khotmul.anggota_id', 'like', '%' . $search . '%')
                    ->orWhere('daurah_anggota.name', 'like', '%' . $search . '%');
            });
        }
        $data = $query->orderBy('status', 'DESC')->paginate(7);

        return response()->json($data);
    }


    // Simpan daurah baru
    public function store(Request $request)
    {
        $data = $request->all();

        $khatam = Khatam::create($data);

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $khatam
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
            'jumlah_khatam'       => 'required|integer',
            'jumlah_tidak_baca'   => 'required|integer',
            'jumlah_membadalkan'  => 'required|integer',
            'keterangan'          => 'nullable|string',
            'tanggal'             => 'required|date',
        ]);

        // Cari data berdasarkan ID
        $khatam = Khatam::findOrFail($id);
        // Update data
        $khatam->update($validated);

        return response()->json([
            'message' => 'Data berhasil diupdate',
            'data'    => $khatam
        ]);
    }


    // Hapus daurah
    public function destroy($id)
    {
        $daurah = Khatam::find($id);
        if (!$daurah) {
            return response()->json(['message' => 'Daurah not found'], 404);
        }

        $daurah->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
}
