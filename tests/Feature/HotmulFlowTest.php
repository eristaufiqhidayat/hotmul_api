<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\HafalanLog;
use App\Models\JuzAssignment;
use App\Models\JuzReference;
use App\Models\User;
use App\Services\KhotmulService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HotmulFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kolom/tabel yang dipakai kode tetapi belum ada di folder migrations
        // (di server sudah dibuat manual).
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
        Schema::create('logs_crond', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date');
            $table->text('message');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeGroup(int $members): array
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@x.test',
            'password' => bcrypt('secret'), 'role' => 'admin',
        ]);
        $group = Group::create(['name' => 'Grup A', 'start_date' => now(), 'created_by' => $admin->id]);

        $users = [];
        for ($i = 1; $i <= $members; $i++) {
            $users[] = User::create([
                'name' => "Anggota $i", 'email' => "a$i" . uniqid() . '@x.test',
                'password' => bcrypt('secret'), 'role' => 'member', 'group_id' => $group->id,
            ]);
        }

        return [$admin, $group, $users];
    }

    public function test_periode_pertama_langsung_membagi_juz_dan_berlangsung_14_hari(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        [, $group] = $this->makeGroup(5);

        app(KhotmulService::class)->runNextPeriode();

        $periode = DB::table('khotmul_periode')->first();
        $this->assertSame(1, (int) $periode->periode);
        $this->assertSame('2026-10-14', Carbon::parse($periode->tanggal_selesai)->toDateString());

        $juz = JuzAssignment::where('group_id', $group->id)->where('period_index', 1)->pluck('juz_number');
        $this->assertCount(5, $juz);
        $this->assertCount(5, $juz->unique());
    }

    public function test_cron_tiap_menit_tidak_membuat_periode_beruntun(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $this->makeGroup(3);
        $service = app(KhotmulService::class);
        $service->runNextPeriode();

        // hari terakhir periode masih termasuk periode berjalan
        Carbon::setTestNow('2026-10-14 23:59:00');
        $service->runNextPeriode();
        $this->assertSame(1, DB::table('khotmul_periode')->count());

        // lewat tengah malam → periode 2, lalu cron berikutnya tidak menambah lagi
        Carbon::setTestNow('2026-10-15 00:01:00');
        $service->runNextPeriode();
        Carbon::setTestNow('2026-10-15 00:02:00');
        $service->runNextPeriode();

        $this->assertSame(2, DB::table('khotmul_periode')->count());
        $p2 = DB::table('khotmul_periode')->where('periode', 2)->first();
        $this->assertSame('2026-10-15', Carbon::parse($p2->tanggal_mulai)->toDateString());
        $this->assertSame('2026-10-28', Carbon::parse($p2->tanggal_selesai)->toDateString());
    }

    public function test_rolling_menggeser_juz_dan_anggota_baru_dapat_juz_kosong(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        [, $group, $users] = $this->makeGroup(3);
        $service = app(KhotmulService::class);
        $service->runNextPeriode();

        $sebelum = JuzAssignment::where('period_index', 1)->pluck('juz_number', 'user_id');

        $baru = User::create([
            'name' => 'Baru', 'email' => 'baru@x.test', 'password' => bcrypt('x'),
            'role' => 'member', 'group_id' => $group->id,
        ]);

        Carbon::setTestNow('2026-10-15 00:01:00');
        $service->runNextPeriode();

        $sesudah = JuzAssignment::where('period_index', 2)->pluck('juz_number', 'user_id');
        foreach ($users as $u) {
            $this->assertSame($sebelum[$u->id] % 30 + 1, (int) $sesudah[$u->id]);
        }
        $this->assertArrayHasKey($baru->id, $sesudah->all());
        $this->assertCount(4, $sesudah->unique());
        $this->assertSame(3, JuzAssignment::where('period_index', 1)->where('status', 'late')->count());
    }

    public function test_setoran_pertama_tidak_langsung_selesai(): void
    {
        [, $group, $users] = $this->makeGroup(1);
        JuzReference::create(['juz_number' => 1, 'total_ayat' => 20]);
        $a = JuzAssignment::create([
            'group_id' => $group->id, 'user_id' => $users[0]->id, 'juz_number' => 1,
            'start_date' => now(), 'end_date' => now()->addDays(13), 'period_index' => 1, 'status' => 'active',
        ]);
        Sanctum::actingAs($users[0]);

        $this->postJson('/api/v1/hafalan', ['assignment_id' => $a->id, 'ayat_from' => 1, 'ayat_to' => 10])
            ->assertOk()->assertJson(['total_ayat' => 10, 'status_assignment' => 'active']);

        $this->postJson('/api/v1/hafalan', ['assignment_id' => $a->id, 'ayat_from' => 1, 'ayat_to' => 25])
            ->assertStatus(422);

        // hari berikutnya: rentang yang beririsan tidak dihitung dua kali
        Carbon::setTestNow(now()->addDay());
        $this->postJson('/api/v1/hafalan', ['assignment_id' => $a->id, 'ayat_from' => 5, 'ayat_to' => 20])
            ->assertOk()->assertJson(['total_ayat' => 20, 'status_assignment' => 'done']);
    }

    public function test_hafalan_today_tanpa_grup_atau_periode_tidak_error(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@x.test', 'password' => bcrypt('x'), 'role' => 'member']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/hafalan/today')->assertOk()->assertJson(['group_id' => null, 'data' => []]);

        [, $group, $users] = $this->makeGroup(1);
        Sanctum::actingAs($users[0]);
        $this->getJson('/api/v1/hafalan/today')->assertOk()->assertJson(['group_id' => $group->id, 'data' => []]);
    }

    public function test_register_tidak_bisa_memilih_role_admin(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Nakal', 'email' => 'nakal@x.test', 'password' => 'secret1',
            'role' => 'admin', 'group_id' => '0',
        ])->assertCreated();

        $user = User::where('email', 'nakal@x.test')->first();
        $this->assertSame('member', $user->role);
        $this->assertNull($user->group_id);
    }

    public function test_anggota_tidak_bisa_mengubah_user_lain_atau_role_sendiri(): void
    {
        [$admin, $group, $users] = $this->makeGroup(2);
        Sanctum::actingAs($users[0]);

        $this->putJson("/api/v1/updateUser/{$users[1]->id}", ['password' => 'baru123'])->assertForbidden();

        $this->putJson("/api/v1/updateUser/{$users[0]->id}", ['name' => 'Ganti', 'role' => 'admin'])->assertOk();
        $this->assertSame('member', $users[0]->fresh()->role);
        $this->assertSame('Ganti', $users[0]->fresh()->name);

        $this->putJson('/api/v1/khatam/update-status', ['assignment_id' => 1, 'status' => 'done'])->assertForbidden();
    }

    public function test_admin_tidak_bisa_mengisi_grup_lebih_dari_30(): void
    {
        [$admin, $group] = $this->makeGroup(30);
        $calon = User::create(['name' => 'C', 'email' => 'c@x.test', 'password' => bcrypt('x'), 'role' => 'member']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/updateUser/{$calon->id}", ['group_id' => $group->id])->assertStatus(422);
        $this->assertNull($calon->fresh()->group_id);
    }

    public function test_endpoint_profile_dan_refresh_ada(): void
    {
        $user = User::create(['name' => 'P', 'email' => 'p@x.test', 'password' => bcrypt('x'), 'role' => 'member']);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->assertJson(['id' => $user->id]);
        $this->withToken($token)->postJson('/api/v1/refresh')->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_notifikasi_h3_dan_h1(): void
    {
        Carbon::setTestNow('2026-10-11 07:00:00');
        [, $group, $users] = $this->makeGroup(1);
        JuzAssignment::create([
            'group_id' => $group->id, 'user_id' => $users[0]->id, 'juz_number' => 1,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-14', 'period_index' => 1, 'status' => 'active',
        ]);

        app(NotificationService::class)->run();
        $this->assertDatabaseHas('notifications', ['user_id' => $users[0]->id, 'title' => 'Deadline H-3']);

        Carbon::setTestNow('2026-10-13 07:00:00');
        app(NotificationService::class)->run();
        $this->assertDatabaseHas('notifications', ['user_id' => $users[0]->id, 'title' => 'Deadline H-1']);
    }
}
