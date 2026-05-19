<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\JuzAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RollingJuzService
{
    public function run()
    {
        DB::beginTransaction();

        try {
            $groups = Group::all();

            foreach ($groups as $group) {

                $lastAssignment = JuzAssignment::where('group_id', $group->id)
                    ->orderByDesc('period_index')
                    ->first();

                if (!$lastAssignment) {
                    continue;
                }

                // ❗ skip kalau belum waktunya
                if (Carbon::parse($lastAssignment->end_date)->isFuture()) {
                    continue;
                }

                $newPeriod = $lastAssignment->period_index + 1;

                // ❗ cegah duplicate rolling
                $exists = JuzAssignment::where('group_id', $group->id)
                    ->where('period_index', $newPeriod)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $startDate = Carbon::parse($lastAssignment->end_date)->addDay();
                $endDate = Carbon::parse($startDate)->addDays(13);

                // update status lama
                JuzAssignment::where('group_id', $group->id)
                    ->where('period_index', $lastAssignment->period_index)
                    ->update(['status' => 'done']);

                $members = GroupMember::where('group_id', $group->id)->get();

                foreach ($members as $member) {

                    $juz = ($member->order_number + $newPeriod - 1) % 30;
                    $juz = $juz == 0 ? 30 : $juz;

                    JuzAssignment::create([
                        'group_id' => $group->id,
                        'user_id' => $member->user_id,
                        'juz_number' => $juz,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'period_index' => $newPeriod,
                        'status' => 'active'
                    ]);
                }
            }

            DB::commit();

            return "Rolling sukses!";
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
        }
    }
}