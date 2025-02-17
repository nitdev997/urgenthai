<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Botble\Ecommerce\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // index
    public function index () {
        $user = auth()->user();

        // Get notifications
        $notifications = Notification::where('customer_id', $user->id)->latest('id')->paginate(20);
        
        return response()->json([
            'success' => true,
            'message' => 'Notifications',
            'data' => $notifications->items(),
        ]);
    }
}
