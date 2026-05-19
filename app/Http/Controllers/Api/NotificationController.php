<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return \App\Models\Notification::where('user_id', auth()->id())
            ->latest()
            ->get();
    }
}
