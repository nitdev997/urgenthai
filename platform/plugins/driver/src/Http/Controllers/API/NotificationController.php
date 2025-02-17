<?php

namespace Botble\Driver\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\Notification;

class NotificationController extends BaseController
{
    // index
    public function index() {
        $user = auth()->user();

        // Get notifications
        $notifications = Notification::where('customer_id', $user->id)->where('prefix', 'driver')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Notifications',
            'data' => $notifications
        ]);
    }
}
