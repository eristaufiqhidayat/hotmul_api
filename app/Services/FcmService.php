<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim push notification lewat FCM HTTP v1.
 *
 * Tidak melakukan apa-apa selama FCM_PROJECT_ID / FCM_CREDENTIALS belum
 * diisi di .env, jadi notifikasi tetap tersimpan di tabel `notifications`.
 */
class FcmService
{
    public function aktif(): bool
    {
        $path = config('services.fcm.credentials');

        return config('services.fcm.project_id') && $path && is_readable($path);
    }

    public function kirimKeUser($userId, string $title, string $body, array $data = []): void
    {
        if (!$this->aktif()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)->pluck('token');

        foreach ($tokens as $token) {
            $this->kirim($token, $title, $body, $data);
        }
    }

    public function kirim(string $token, string $title, string $body, array $data = []): void
    {
        try {
            $accessToken = $this->accessToken();
            $projectId = config('services.fcm.project_id');

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'high'],
                    ],
                ]);

            // token sudah tidak berlaku (aplikasi dihapus / logout)
            if (in_array($response->status(), [404, 410]) ||
                str_contains($response->body(), 'UNREGISTERED')) {
                DeviceToken::where('token', $token)->delete();
            } elseif ($response->failed()) {
                Log::warning('FCM gagal', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * OAuth2 access token dari service account (JWT RS256), disimpan 50 menit.
     */
    private function accessToken(): string
    {
        return Cache::remember('fcm-access-token', 3000, function () {
            $sa = json_decode(file_get_contents(config('services.fcm.credentials')), true);
            $now = time();

            $enc = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $header = $enc(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $enc(json_encode([
                'iss' => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign("$header.$claim", $signature, $sa['private_key'], 'sha256WithRSAEncryption');
            $jwt = "$header.$claim." . $enc($signature);

            return Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');
        });
    }
}
