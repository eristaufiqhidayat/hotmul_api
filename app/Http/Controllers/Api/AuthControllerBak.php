<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class AuthControllerBak extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $datePart = date('ymd');
        $randomPart = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $anggota_id = $datePart . $randomPart;
        $lastDaurahId = DB::table('daurah')->max('id');
        try {
            return DB::transaction(function () use ($validated, $anggota_id, $lastDaurahId) {

                // --- Buat user dulu ---
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'anggota_id' => $anggota_id,
                    'group_id' => 2,
                    'daurah_id' => $lastDaurahId, // ✅ TAMBAH INI
                ]);

                if (!$user) {
                    return response()->json([
                        'message' => 'Gagal membuat user'
                    ], 500);
                }

                // --- Proses daurah ---
                $lastGroupId = DB::table('daurah_anggota')->max('group_id');
                if (!$lastGroupId) {
                    return response()->json([
                        'message' => 'Belum ada data daurah_anggota'
                    ], 404);
                }

                $jumlahAnggota = DB::table('users')
                    ->where('daurah_id', $lastGroupId)
                    ->count('id');

                if ($jumlahAnggota >= 30) {
                    $lastGroupId++;
                    DB::table('daurah')->insert([
                        'group_id' => $lastGroupId,
                        'group_name' => "Daurah " . $lastGroupId
                    ]);
                }

                // DB::table('daurah_anggota')->insert([
                //     'user_id' => $anggota_id,   // ✅ gunakan id dari tabel users
                //     'group_id' => $lastGroupId,
                //     'name' => $validated['name'],
                // ]);

                // === Tambah notifikasi ke messages ===
                $messageId = DB::table('messages')->insertGetId([
                    'sender_id' => 6, // misalnya admin (bisa diganti sesuai kebutuhan)
                    'target_type' => 'user',
                    'target_id' => $user->id,
                    'content' => "Selamat bergabung, Anda masuk ke Daurah ID: " . $lastGroupId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('message_user')->insert([
                    'message_id' => $messageId,
                    'user_id' => $user->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // --- Proses khotmul ---
                $periodeTerakhir = DB::table('khotmul')
                    ->where('group_id', $lastGroupId)
                    ->max('periode');

                $periodeBaru = $periodeTerakhir ? $periodeTerakhir + 1 : 1;

                if (!$periodeTerakhir) {
                    $insertData = [];
                    for ($i = 1; $i <= 30; $i++) {
                        $insertData[] = [
                            'group_id' => $lastGroupId,
                            'juz' => $i,
                            'periode' => $periodeBaru,
                            'tanggal' => date('Y-m-d'),
                            'anggota_id' => $i == 1 ? $anggota_id : null,
                        ];
                    }
                    DB::table('khotmul')->insert($insertData);
                } else {
                    $updated = DB::table('khotmul')
                        ->where('group_id', $lastGroupId)
                        ->where('periode', $periodeTerakhir)
                        ->whereNull('anggota_id')
                        ->orderBy('juz', 'asc')
                        ->limit(1)
                        ->update([
                            'anggota_id' => $anggota_id
                        ]);

                    if (!$updated) {
                        $insertData = [];
                        for ($i = 1; $i <= 30; $i++) {
                            $insertData[] = [
                                'group_id' => $lastGroupId,
                                'juz' => $i,
                                'periode' => $periodeBaru,
                                'tanggal' => date('Y-m-d'),
                                'anggota_id' => $i == 1 ? $anggota_id : null,
                                'status' => $i == 1 ? 'diambil' : 'kosong',
                            ];
                        }
                        DB::table('khotmul')->insert($insertData);
                    }
                }

                return response()->json([
                    'message' => 'User registered successfully',
                    'user' => $user,
                    'daurah_id' => $lastGroupId, // ✅ bisa dikembalikan juga
                ], 201);
            });
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error saat membuat user',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function addUserAnggota(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'anggota_id' => $request->input('anggota_id'),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'User registered successfully'], 201);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // 🔥 cari user manual
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 🔥 hapus token lama (optional tapi recommended)
        $user->tokens()->delete();

        // 🔥 generate token Sanctum
        $accessToken = $user->createToken('mobile')->plainTextToken;

        // ambil data user
        $name = $user->name;
        $email = $user->email;
        $anggota_id = $user->anggota_id;
        $group_id = $user->group_id;
        $user_id = $user->id;
        $daurah_id = $user->daurah_id;


        $periodeTerakhir = DB::table('khotmul')
            ->where('anggota_id', $anggota_id)
            ->max('periode');

        $juzTerakhir = DB::table('khotmul')
            ->where('anggota_id', $anggota_id)
            ->where('periode', $periodeTerakhir)
            ->value('juz');

        return response()->json([
            'access_token' => $accessToken,

            // ❌ dihapus karena Sanctum tidak pakai ini
            // 'refresh_token' => null,
            // 'expires_in' => null,

            'token_type' => 'Bearer',

            'name' => $name,
            'anggota_id' => $anggota_id,
            'group_id' => $group_id,
            'daurah_id' => $daurah_id,
            'email' => $email,
            'user_id' => $user_id,
            'juz' => $juzTerakhir,
        ]);
    }


    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function logout(Request $request)
    {
        // hapus token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }

    public function refresh()
    {

        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }
    public function cekAnggota(Request $request)
    {
        $anggotaId = $request->input('anggota_id'); // ambil dari request body/query

        // Validasi request minimal
        if (!$anggotaId) {
            return response()->json([
                'status' => false,
                'message' => 'anggota_id tidak ditemukan di request'
            ], 400);
        }

        // Cek ke database (kolom anggota_id di tabel users)
        $user = \App\Models\User::where('anggota_id', $anggotaId)->first();

        if ($user) {
            return response()->json([
                'status' => true,
                'role' => 'anggota',
                'message' => 'anggota_id ditemukan',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'anggota_id' => $user->anggota_id,
                    'group_id' => $user->group_id
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'role' => 'admin',
                'message' => 'anggota_id tidak ada di database'
            ]);
        }
    }
    public function update(Request $request, $id)
    {
        \Log::info('Request headers: ', $request->headers->all());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Request input: ', $request->input());
        \Log::info('Request all: ', $request->all());

        $anggota = User::findOrFail($id);

        // Update email, name, group_id
        $anggota->email = $request->email ?? $anggota->email;
        $anggota->name = $request->name ?? $anggota->name;
        $anggota->group_id = $request->group_id ?? $anggota->group_id;
        $anggota->daurah_id = $request->daurah_id ?? $anggota->daurah_id;

        // Hanya update password jika ada input baru
        if ($request->filled('password')) {
            $anggota->password = Hash::make($request->password);
        }

        $anggota->save();

        return response()->json($anggota);
    }
}
