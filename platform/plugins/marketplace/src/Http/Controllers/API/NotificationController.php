<?php

namespace Botble\Marketplace\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // index
    public function index () {
        $user = auth()->user();

        // Get notifications
        $notifications = $user->getNotifications()->latest('id')->paginate(20);
        
        return response()->json([
            'success' => true,
            'message' => 'Notifications',
            'data' => $notifications->items(),
        ]);
    }
}
