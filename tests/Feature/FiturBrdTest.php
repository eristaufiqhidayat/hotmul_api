<?php

namespace Tests\Feature;

use App\Models\BadalAssignment;
use App\Models\Group;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use App\Models\JuzReference;
use App\Models\KasTransaksi;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiturBrdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // kolom/tabel yang di server dibuat manual (lihat HotmulFlowTest)
        Schema::table('users', function (Blueprint $table) {
            $table->integer('group_id')->nullable();
            $table->bigInteger('anggota_id')->nullable();
        });
        Schema::drop('khotmul_periode');
        Schema::create('khotmul_periode', function (Blueprint $table) {
            $table->id();
            $table->integer('periode');
            $table->integer('group_id')->nullable();
            $table->dateTime('tanggal')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Carbon::setTestNow('2026-10-20 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function grup(int $anggota): array
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@x.test',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
        $group = Group::create(['name' => 'Al-Fatih', 'start_date' => now(), 'created_by' => $admin->id]);
        $admin->update(['group_id' => $group->id]);

        $users = [];
        for ($i = 1; $i <= $anggota; $i++) {
            $users[] = User::create([
                'name' => "Anggota $i", 'email' => "a$i" . uniqid() . '@x.test',
                'password' => bcrypt('x'), 'role' => 'member', 'group_id' => $group->id,
            ]);
        }

        return [$admin, $group, $users];
    }

    private function juz(Group $group, User $user, int $juz, string $status = 'active', string $mulai = '2026-10-13'): JuzAssignment
    {
        return JuzAssignment::create([
            'group_id' => $group->id, 'user_id' => $user->id, 'juz_number' => $juz,
            'start_date' => $mulai, 'end_date' => Carbon::parse($mulai)->addDays(13),
            'period_index' => 1, 'status' => $status,
        ]);
    }

    public function test_juz_terlambat_bisa_diambil_alih_dan_dapat_badge(): void
    {
        [, $group, [$budi, $citra, $dewi]] = $this->grup(3);
        $this->juz($group, $budi, 2);
        $juzCitra = $this->juz($group, $citra, 3, 'late');
        $this->juz($group, $dewi, 4);

        Sanctum::actingAs($budi);

        $this->getJson('/api/v1/badal')->assertOk()
            ->assertJsonCount(1, 'kandidat')
            ->assertJsonPath('kandidat.0.assignment_id', $juzCitra->id)
            ->assertJsonPath('badge.pengganti_aktif', 0);

        $this->postJson("/api/v1/badal/{$juzCitra->id}/ambil")->assertCreated()
            ->assertJsonPath('badge.pengganti_aktif', 1);

        $this->assertSame(1, UserBadge::where('user_id', $budi->id)->count());
        $this->assertTrue(Notification::where('user_id', $citra->id)->exists());
        $this->assertTrue(Notification::where('user_id', $dewi->id)->where('title', 'like', '%Pengganti Aktif%')->exists());

        // juz yang sama tidak bisa diambil orang lain
        Sanctum::actingAs($dewi);
        $this->postJson("/api/v1/badal/{$juzCitra->id}/ambil")->assertStatus(422);
        $this->getJson('/api/v1/badal')->assertJsonCount(0, 'kandidat');
    }

    public function test_anggota_aktif_tidak_bisa_dibadal_tetapi_tidak_lapor_14_hari_bisa(): void
    {
        [, $group, [$budi, $citra]] = $this->grup(2);
        $juzCitra = $this->juz($group, $citra, 3, 'active', '2026-10-13');
        HafalanLog::create(['assignment_id' => $juzCitra->id, 'user_id' => $citra->id, 'tanggal' => '2026-10-19', 'ayat_from' => 1, 'ayat_to' => 5]);

        Sanctum::actingAs($budi);
        $this->postJson("/api/v1/badal/{$juzCitra->id}/ambil")->assertStatus(422);

        HafalanLog::query()->delete();
        $juzCitra->update(['start_date' => '2026-10-01', 'end_date' => '2026-10-28']);
        $this->getJson('/api/v1/badal')->assertJsonPath('kandidat.0.hari_tidak_lapor', 19);
        $this->postJson("/api/v1/badal/{$juzCitra->id}/ambil")->assertCreated();
    }

    public function test_pengganti_bisa_setor_juz_terlambat_dan_badal_selesai(): void
    {
        [, $group, [$budi, $citra]] = $this->grup(2);
        JuzReference::create(['juz_number' => 3, 'total_ayat' => 10]);
        $juzCitra = $this->juz($group, $citra, 3, 'late');

        Sanctum::actingAs($budi);
        $this->postJson('/api/v1/hafalan', ['assignment_id' => $juzCitra->id, 'ayat_from' => 1, 'ayat_to' => 10])
            ->assertForbidden();

        $this->postJson("/api/v1/badal/{$juzCitra->id}/ambil")->assertCreated();
        $this->postJson('/api/v1/hafalan', ['assignment_id' => $juzCitra->id, 'ayat_from' => 1, 'ayat_to' => 10])
            ->assertOk()->assertJson(['status_assignment' => 'done']);

        $this->assertSame('done', BadalAssignment::first()->status);
    }

    public function test_admin_diberi_tahu_anggota_tidak_aktif_14_hari(): void
    {
        [$admin, $group, [$budi, $citra]] = $this->grup(2);
        $this->juz($group, $budi, 1, 'active', '2026-10-01');
        $a = $this->juz($group, $citra, 2, 'active', '2026-10-01');
        HafalanLog::create(['assignment_id' => $a->id, 'user_id' => $citra->id, 'tanggal' => '2026-10-19']);

        app(NotificationService::class)->notifAdminAnggotaTidakAktif();
        app(NotificationService::class)->notifAdminAnggotaTidakAktif();

        $notif = Notification::where('user_id', $admin->id)->get();
        $this->assertCount(1, $notif);
        $this->assertStringContainsString('Anggota 1', $notif[0]->title);
    }

    public function test_kas_saldo_verifikasi_dan_export(): void
    {
        [$admin, , [$budi]] = $this->grup(1);

        Sanctum::actingAs($budi);
        $this->postJson('/api/v1/kas', ['tanggal' => '2026-10-12', 'nominal' => 100000, 'metode' => 'QRIS'])
            ->assertCreated();
        $this->postJson('/api/v1/kas', ['jenis' => 'keluar', 'tanggal' => '2026-10-12', 'nominal' => 5000])
            ->assertForbidden();
        $this->getJson('/api/v1/kas')->assertJson(['saldo' => 0, 'menunggu_verifikasi' => 1]);

        Sanctum::actingAs($admin);
        $id = KasTransaksi::first()->id;
        $this->postJson("/api/v1/kas/{$id}/verifikasi")->assertOk();
        $this->postJson('/api/v1/kas', ['jenis' => 'keluar', 'tanggal' => '2026-10-13', 'nominal' => 25000, 'keterangan' => 'Konsumsi'])
            ->assertCreated();

        $this->getJson('/api/v1/kas')->assertJson(['saldo' => 75000, 'masuk' => 100000, 'keluar' => 25000, 'menunggu_verifikasi' => 0])
            ->assertJsonCount(2, 'data');

        $csv = $this->get('/api/v1/kas/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Konsumsi', $csv);
        $this->assertStringContainsString('75000', $csv);

        Sanctum::actingAs($budi);
        $this->postJson("/api/v1/kas/{$id}/verifikasi")->assertForbidden();
        $this->deleteJson("/api/v1/kas/{$id}")->assertForbidden();
    }

    public function test_upload_dokumentasi_khotaman_maks_50mb(): void
    {
        Storage::fake('public');
        [, $group, [$budi, $citra]] = $this->grup(2);
        DB::table('khotmul_periode')->insert(['periode' => 7, 'status' => 'active', 'tanggal_mulai' => '2026-10-13', 'tanggal_selesai' => '2026-10-26']);

        Sanctum::actingAs($budi);
        $this->post('/api/v1/dokumentasi', [
            'file' => UploadedFile::fake()->image('khotaman.jpg'),
            'deskripsi' => 'Khotaman di masjid',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/v1/dokumentasi', [
            'file' => UploadedFile::fake()->create('besar.mp4', 51201, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->getJson('/api/v1/dokumentasi')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.jenis', 'foto')
            ->assertJsonPath('data.0.period_index', 7)
            ->assertJsonPath('periode_aktif.periode', 7);

        $id = DB::table('dokumentasi_khotaman')->value('id');
        Sanctum::actingAs($citra);
        $this->deleteJson("/api/v1/dokumentasi/{$id}")->assertForbidden();
    }

    public function test_jadwal_bergulir_menampilkan_juz_berikutnya(): void
    {
        [, $group, [$budi]] = $this->grup(1);
        $a = $this->juz($group, $budi, 30, 'active', '2026-10-13');
        HafalanLog::create(['assignment_id' => $a->id, 'user_id' => $budi->id, 'tanggal' => '2026-10-14']);

        Sanctum::actingAs($budi);
        $res = $this->getJson('/api/v1/jadwal-bergulir')->assertOk()
            ->assertJsonPath('sekarang.juz', 30)
            ->assertJsonCount(14, 'hari')
            ->assertJsonPath('hari.1.sudah_lapor', true)
            ->assertJsonPath('berikutnya.0.juz', 1)
            ->assertJsonPath('berikutnya.0.start_date', '2026-10-27')
            ->assertJsonPath('berikutnya.1.juz', 2);

        $this->assertTrue($res->json('hari.7.hari_ini'));
    }

    public function test_simpan_token_perangkat(): void
    {
        [, , [$budi]] = $this->grup(1);
        Sanctum::actingAs($budi);

        $this->postJson('/api/v1/device-token', ['token' => 'abc', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/device-token', ['token' => 'abc', 'platform' => 'android'])->assertOk();
        $this->assertSame(1, DB::table('device_tokens')->count());
    }
}
