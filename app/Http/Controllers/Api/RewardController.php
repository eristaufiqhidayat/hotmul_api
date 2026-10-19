<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    // List semua daurah
public function index(Request $request)
{

    $query = Reward::query()
        ->join('users', 'reward.anggota_id', '=', 'users.id')
        ->select(
            'reward.id',
            'reward.anggota_id',
            'reward.keterangan',
            'reward.rp',
            'users.name as anggota_name'
        );
    // anggota hanya melihat reward miliknya; admin melihat semua
    if (auth()->user()->role !== 'admin') {
        $query->where('reward.anggota_id', auth()->id());
    }

    $data = $query->orderBy('reward.id', 'desc')->paginate(7);


    return response()->json($data);
}


    // Simpan daurah baru
public function store(Request $request)
{
    $data = $request->validate([
        'anggota_id' => 'required|integer|exists:users,id',
        'keterangan' => 'nullable|string|max:200',
        'rp'         => 'required|numeric|min:0',
    ]);

    $donasi = Reward::create($data);

    return response()->json([
        'message' => 'Data berhasil disimpan',
        'data' => $donasi
    ]);
}

    // Tampilkan 1 daurah
    public function show($id)
    {
        $reward = Reward::find($id);
        if (!$reward) {
            return response()->json(['message' => 'Daurah not found'], 404);
        }
        return response()->json($reward);
    }

    // Update daurah
  public function update(Request $request, $id)
{
    // Validasi data yang masuk
    $validated = $request->validate([
        'user_id'             => 'required|integer',
        'rp'   => 'required',
    ]);

    // Cari data berdasarkan ID
    $reward = Reward::findOrFail($id);
    // Update data
    $reward->update($validated);

    return response()->json([
        'message' => 'Data berhasil diupdate',
        'data'    => $reward
    ]);
}


    // Hapus daurah
    public function destroy($id)
    {
        $reward = Reward::find($id);
        if (!$reward) {
            return response()->json(['message' => 'Donasi not found'], 404);
        }

        $reward->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
}
