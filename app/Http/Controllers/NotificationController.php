<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->where(function ($query) {
                $query->where('read', false)
                      ->orWhere('created_at', '>=', Carbon::now()->subWeek());
            })
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($notifications);
    }

    public function update(Notification $notification)
    {
        $notification->update(['read' => true]);
        return response()->json(['message' => 'Notification marked as read.']);
    }
}
