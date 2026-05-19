<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Anggota;
use App\Models\User;

//test ssh
class AnggotaController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();
        if($request->group_id){  
          $query->where('group_id', '=',  $request->group_id );
        }

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $anggota = $query->orderBy('name', 'desc')->paginate(30);
        return response()->json($anggota);
    }
    public function indexByGroup($group_id)
    {
        $query = Anggota::query();
        $query->where('group_id', '=', '$group_id');
        $anggota = $query->orderBy('user_id', 'desc')->paginate(7);
        return response()->json($anggota);
    }
    public function index2(Request $request)
    {
        $anggota = User::select('id', 'name')
            ->orderBy('name', 'desc')
            ->get();
        return response()->json($anggota);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'group_id' => 'required|string',
        ]);

        $anggota = Anggota::create($request->all());
        return response()->json($anggota, 201);
    }

    public function show($id)
    {
        return response()->json(Anggota::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        \Log::info('Request headers: ', $request->headers->all());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Request input: ', $request->input());
        \Log::info('Request all: ', $request->all());

        $anggota = Anggota::findOrFail($id);
        $anggota->update($request->only(['user_id', 'group_id', 'name']));
        return response()->json($anggota);
    }

    public function destroy($id)
    {
        $anggota = Anggota::findOrFail($id);
        $anggota->delete();
        return response()->json(null, 204);
    }
}
