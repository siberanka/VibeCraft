<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notices = $user->notifications()->latest()->limit(50)->get();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('theme::account.notifications', ['notices' => $notices]);
    }
}
