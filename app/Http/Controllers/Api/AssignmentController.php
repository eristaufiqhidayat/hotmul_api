<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JuzAssignment;

class AssignmentController extends Controller
{
    public function active(Request $request)
    {
        $assignment = JuzAssignment::where('user_id', auth()->id())
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'No active assignment'
            ], 404);
        }

        return response()->json([
            'assignment_id' => $assignment->id,
            'juz' => $assignment->juz_number,
            'start_date' => $assignment->start_date,
            'end_date' => $assignment->end_date,
        ]);
    }
    public function updateStatus(Request $request)
    {
        try {

            // $request->validate([
            //     'name'   => 'required',
            //     'juz'    => 'required',
            //     'status' => 'required',
            // ]);

            $data = JuzAssignment::leftJoin(
                'users',
                'juz_assignments.user_id',
                '=',
                'users.id'
            )
                ->where('juz_assignments.id', $request->assignment_id)
                ->select('juz_assignments.*')
                ->first();

            if (!$data) {

                return response()->json([
                    'success' => false,
                    'message' => $request->assignment_id                        ? 'Assignment not found'
                        : 'Assignment ID is required',
                    'assignment_id' => $request->assignment_id,
                    'status' => $request->status
                ], 404);
            }

            $data->status = $request->status;

            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diupdate',
                'data'    => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'assignment_id' => $request->assignment_id,
                'status' => $request->status
            ], 500);
        }
    }
}
