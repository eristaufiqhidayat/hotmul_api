<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\JuzAssignment;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GroupSetupSeeder extends Seeder
{
    public function run(): void
    {
        // =========================
        // 1. BUAT ADMIN
        // =========================
        $admin = User::create([
            'name' => 'Admin Tahfidz',
            'email' => 'admin2@tahfidz.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);

        // =========================
        // 2. BUAT GROUP
        // =========================
        $group = Group::create([
            'name' => 'Group Tahfidz 1',
            'start_date' => Carbon::today(),
            'created_by' => $admin->id
        ]);

        // =========================
        // 3. BUAT 30 MEMBER
        // =========================
        $members = [];

        for ($i = 32; $i <= 62; $i++) {
            $members[] = User::create([
                'name' => "Member $i",
                'email' => "member$i@mail.com",
                'password' => Hash::make('password'),
                'role' => 'member'
            ]);
        }

        // =========================
        // 4. MASUKKAN KE GROUP + ORDER
        // =========================
        foreach ($members as $index => $member) {

            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $member->id,
                'order_number' => $index + 1, // 1–30
                'juz_start' => $index + 1
            ]);
        }

        // =========================
        // 5. ASSIGN JUZ PERIODE AWAL
        // =========================
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(13); // 2 minggu

        foreach ($members as $index => $member) {

            $juz = $index + 1; // awal: 1–30

            JuzAssignment::create([
                'group_id' => $group->id,
                'user_id' => $member->id,
                'juz_number' => $juz,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_index' => 1,
                'status' => 'active'
            ]);
        }

        $this->command->info('Seeder berhasil: 30 anggota + assignment awal dibuat!');
    }
}