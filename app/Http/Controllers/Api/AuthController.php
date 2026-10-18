<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function register(Request $request, AuthService $service)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'nullable|in:admin,member',
            'group_id' => 'nullable|integer',
        ]);

        // pendaftar selalu anggota tanpa grup; admin yang menentukan
        // role dan grupnya lewat updateUser
        $request->merge(['role' => 'member', 'group_id' => null]);

        try {
            $user = $service->register($request->all());

            return response()->json([
                'message' => 'Registrasi berhasil, OTP telah dikirim ke email',
                'email'   => $user->email,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah'
            ], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        // hapus token saat ini
        $request->user()->currentAccessToken()->delete();
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }
    public function userProfile(Request $request)
    {
        return response()->json($request->user());
    }

    public function refresh(Request $request)
    {
        // ganti token yang sedang dipakai dengan token baru
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'access_token' => $user->createToken('mobile')->plainTextToken,
            'token_type' => 'bearer',
        ]);
    }

    public function cekAnggota(Request $request)
    {
        $anggotaId = $request->input('anggota_id');

        if (!$anggotaId) {
            return response()->json([
                'status' => false,
                'message' => 'anggota_id tidak ditemukan di request'
            ], 400);
        }

        $user = User::where('anggota_id', $anggotaId)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'role' => 'admin',
                'message' => 'anggota_id tidak ada di database'
            ]);
        }

        return response()->json([
            'status' => true,
            'role' => 'anggota',
            'message' => 'anggota_id ditemukan',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anggota_id' => $user->anggota_id,
                'group_id' => $user->group_id
            ]
        ]);
    }

    public function addUserAnggota(Request $request)
    {
        $this->pastikanAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'member',
        ]);
        $user->anggota_id = $request->input('anggota_id');
        $user->save();

        return response()->json(['message' => 'User registered successfully', 'id' => $user->id]);
    }

    public function update(Request $request, $id)
    {
        $me = $request->user();
        $isAdmin = $me->role === 'admin';

        // anggota hanya boleh mengubah datanya sendiri
        if (!$isAdmin && (int) $id !== (int) $me->id) {
            return response()->json([
                'message' => 'Hanya admin yang boleh mengubah data anggota lain'
            ], 403);
        }

        $anggota = User::findOrFail($id);

        $request->validate([
            'email' => 'nullable|email|unique:users,email,' . $anggota->id,
            'role' => 'nullable|in:admin,member',
            'group_id' => 'nullable|integer|min:0',
        ]);

        // Update email, name
        $anggota->email = $request->email ?? $anggota->email;
        $anggota->name = $request->name ?? $anggota->name;

        // role dan grup hanya diatur admin
        if ($isAdmin) {
            $anggota->role = $request->role ?? $anggota->role;

            if ($request->filled('group_id')) {
                $groupId = (int) $request->group_id ?: null;

                // README: maksimal 30 anggota per grup
                if ($groupId && (int) $anggota->group_id !== $groupId) {
                    $jumlah = User::where('group_id', $groupId)->count();
                    if ($jumlah >= 30) {
                        return response()->json([
                            'message' => 'Grup sudah berisi 30 anggota'
                        ], 422);
                    }
                }

                $anggota->group_id = $groupId;
            }
        }

        // Hanya update password jika ada input baru
        if ($request->filled('password')) {
            $anggota->password = Hash::make($request->password);
        }

        $anggota->save();

        return response()->json($anggota);
    }
}
