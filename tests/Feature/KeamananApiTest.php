<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KeamananApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'member'): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role . uniqid() . '@x.test',
            'password' => bcrypt('secret'), 'role' => $role,
        ]);
    }

    public function test_route_uji_publik_sudah_dihapus(): void
    {
        $this->get('/test-rolling')->assertNotFound();
        $this->get('/test-notif')->assertNotFound();
    }

    public function test_route_sync_periode_sudah_dihapus(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->getJson('/api/v1/khotmulPeriodeSync')->assertNotFound();
        $this->getJson('/api/v1/khotmulPeriodeCreate')->assertNotFound();
    }

    public function test_anggota_tidak_boleh_memanggil_endpoint_tulis_admin(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/v1/anggota', ['name' => 'x'])->assertForbidden();
        $this->putJson('/api/v1/daurah/1', [])->assertForbidden();
        $this->deleteJson('/api/v1/reward/1')->assertForbidden();
        $this->postJson('/api/v1/jadwal', [])->assertForbidden();
        $this->deleteJson('/api/v1/donasi/1')->assertForbidden();
        $this->deleteJson('/api/v1/khotmul-periode/1')->assertForbidden();
        $this->getJson('/api/v1/khotmul/generateData')->assertForbidden();
    }

    public function test_anggota_tidak_boleh_membaca_inbox_user_lain(): void
    {
        $saya = $this->user();
        $lain = $this->user();
        Sanctum::actingAs($saya);

        $this->getJson("/api/v1/messages/inbox/{$lain->id}")->assertForbidden();
        $this->getJson("/api/v1/messages/countUnread/{$lain->id}")->assertForbidden();
    }

    public function test_anggota_tidak_boleh_kirim_pesan_ke_semua(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/v1/messages/send', [
            'target_type' => 'all',
            'content' => 'halo',
        ])->assertForbidden();
    }
}
