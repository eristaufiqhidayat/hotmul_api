<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\groupUser;
use Illuminate\Http\Request;

class groupUserController extends Controller
{
    // List semua daurah
    public function index(Request $request)
    {
        $query = groupUser::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('id', 'like', '%' . $request->search . '%');
        }

        $daurah = $query->orderBy('id', 'desc')->paginate(7);
        return response()->json($daurah);
    }

    // Simpan daurah baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $groupUser = groupUser::create($request->only('name'));
        return response()->json($groupUser, 201);
    }

    // Tampilkan 1 daurah
    public function show($id)
    {
        $groupUser = groupUser::find($id);
        if (!$groupUser) {
            return response()->json(['message' => 'Group not found'], 404);
        }
        return response()->json($groupUser);
    }

    // Update daurah
    public function update(Request $request, $id)
    {
        $groupUser = groupUser::find($id);
        if (!$groupUser) {
            return response()->json(['message' => 'group not found'], 404);
        }

        $request->validate([
            'group_name' => 'required|string|max:255',
        ]);
    \Log::info('Request headers: ', $request->headers->all());
    \Log::info('Request method: ' . $request->method());
    \Log::info('Request input: ', $request->input());
    \Log::info('Request all: ', $request->all());

        $groupUser->update($request->only('group_name'));
        return response()->json($groupUser);
    }

    // Hapus daurah
    public function destroy($id)
    {
        $groupUser = groupUser::find($id);
        if (!$groupUser) {
            return $groupUser()->json(['message' => 'Group not found'], 404);
        }

        $groupUser->delete();
        return response()->json(['message' => 'Daurah deleted successfully']);
    }
}
