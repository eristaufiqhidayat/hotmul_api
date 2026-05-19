<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Daurah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DaurahController extends Controller
{
    // List semua daurah
    public function index(Request $request)
    {
        $data = Daurah::withCount('users')->get();

        return response()->json([
            'data' => $data
        ]);
    }
    public function periodeDaurah()
    {
        $groups = DB::table('groups')->get();

        $periodes = DB::table('khotmul_periode')
            ->orderBy('periode')
            ->get();

        $data = $groups->flatMap(function ($group) use ($periodes) {

            return $periodes->map(function ($kp) use ($group) {

                return [
                    'id' => $group->id,
                    'name' => $group->name,

                    'periode_group' =>
                    $group->periode + $kp->periode,

                    'periode_khotmul' => $kp->periode,
                ];
            });
        });
        return response()->json([
            'data' => $data
        ]);
    }

    // Simpan daurah baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $daurah = Daurah::create($request->only('name'));
        return response()->json($daurah, 201);
    }

    // Tampilkan 1 daurah
    public function show($id)
    {
        $daurah = Daurah::find($id);
        if (!$daurah) {
            return response()->json(['message' => 'Daurah not found 3'], 404);
        }
        return response()->json($daurah);
    }

    // Update daurah
    public function update(Request $request, $id)
    {
        $daurah = Daurah::find($id);
        if (!$daurah) {
            return response()->json(['message' => 'Daurah not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        \Log::info('Request headers: ', $request->headers->all());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Request input: ', $request->input());
        \Log::info('Request all: ', $request->all());

        $daurah->update($request->only('group_name'));
        return response()->json($daurah);
    }

    // Hapus daurah
    public function destroy($id)
    {
        $daurah = Daurah::find($id);
        if (!$daurah) {
            return response()->json(['message' => 'Daurah not found'], 404);
        }

        $daurah->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
    public function cekJumlahGroupAnggota(Request $request)
    {
        $jumlah = DB::table('groups')
            ->where('group_id', $request->group_id)
            ->count();

        return response()->json([
            'group_id' => $request->group_id,
            'jumlah_group' => $jumlah
        ]);
    }
    public function getDaurahData()
    {
        try {
            $data = DB::table('daurah as d')
                ->select(
                    'd.group_id',
                    'd.group_name',
                    DB::raw('COUNT(DISTINCT da.user_id) AS jumlah_anggota'),
                    DB::raw('COUNT(DISTINCT kp.periode) AS jumlah_periode')
                )
                ->leftJoin('daurah_anggota as da', 'd.group_id', '=', 'da.group_id')
                ->leftJoin('khotmul_periode as kp', 'd.group_id', '=', 'kp.group_id')
                ->groupBy('d.group_id', 'd.group_name')
                ->orderBy('d.group_id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data daurah berhasil diambil'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan data dalam bentuk view (untuk web)
     */
    public function reportDaurah()
    {
        try {
            $daurahData = DB::table('daurah as d')
                ->select(
                    'd.group_id',
                    'd.group_name',
                    DB::raw('COUNT(DISTINCT da.user_id) AS jumlah_anggota'),
                    DB::raw('COUNT(DISTINCT kp.periode) AS jumlah_periode')
                )
                ->leftJoin('daurah_anggota as da', 'd.group_id', '=', 'da.group_id')
                ->leftJoin('khotmul_periode as kp', 'd.group_id', '=', 'kp.group_id')
                ->groupBy('d.group_id', 'd.group_name')
                ->orderBy('d.group_id')
                ->get();

            return view('daurah.index', compact('daurahData'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
