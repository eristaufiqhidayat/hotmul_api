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
        \Log::info('Request headers: ', $request->headers->all());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Request input: ', $request->input());
        \Log::info('Request all: ', $request->all());
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'  => 'required|in:admin,member',
            'group_id'    => 'required', // Validasi untuk nomor HP
        ]);

        try {
            $user = $service->register($request->all());

            return response()->json([
                'message' => 'Registrasi berhasil, OTP telah dikirim ke email',
                'email'   => $user->email,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() . json_encode($request->all())
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
    public function update(Request $request, $id)
    {
        \Log::info('Request headers: ', $request->headers->all());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Request input: ', $request->input());
        \Log::info('Request all: ', $request->all());

        $anggota = User::findOrFail($id);

        // Update email, name, role
        $anggota->email = $request->email ?? $anggota->email;
        $anggota->name = $request->name ?? $anggota->name;
        $anggota->role = $request->role ?? $anggota->role;
        $anggota->group_id = $request->group_id ?? $anggota->group_id;

        // Hanya update password jika ada input baru
        if ($request->filled('password')) {
            $anggota->password = Hash::make($request->password);
        }

        $anggota->save();

        return response()->json($anggota);
    }
}
