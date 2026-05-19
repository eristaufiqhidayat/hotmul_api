<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\MessageUser;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id',
            'target_type' => 'required|in:user,group,all',
            'target_id' => 'nullable|integer',
            'content' => 'required|string',
        ]);

        $message = Message::create([
            'sender_id' => $request->sender_id,
            'target_type' => $request->target_type,
            'target_id' => $request->target_id,
            'content' => $request->get('content'),
        ]);

        $recipients = [];

        if ($request->target_type === 'user') {
            $recipients = [User::findOrFail($request->target_id)];
        } elseif ($request->target_type === 'group') {
            $group = Group::with('users')->findOrFail($request->target_id);
            $recipients = $group->users;
        } else { // all
            $recipients = User::all();
        }

        foreach ($recipients as $user) {
            MessageUser::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => $message
        ]);
    }

    public function inbox($userId)
    {
        $messages = MessageUser::with('message.sender')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($messages);
    }
    public function countUnread($userId)
    {
        $count = DB::table('message_user')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'user_id' => $userId,
            'unread_count' => $count,
        ]);
    }
    public function updateStatus($id)
    {
        
        $updated = DB::table('message_user')
            ->where('id', $id)
            ->update([
                'is_read'    => 1, // isi 0 atau 1
                'updated_at' => date('Y-m-d'),
            ]);


        return response()->json([
            'success' => $updated > 0,
            'message' => $updated ? 'Status berhasil diupdate' : 'Data tidak ditemukan',
        ]);
    }
}
