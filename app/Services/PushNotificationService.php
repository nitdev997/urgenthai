<?php

namespace App\Services;

use OneSignal;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a notification using OneSignal
     *
     * @param string $message The message to be sent
     * @param array $playerIds Array of Player IDs to send the notification to
     * @param string|null $url Optional URL to redirect on click
     * @param array $data Additional payload data
     * @param string $title Notification title
     * @return bool True if successful, false otherwise
     */
    public function sendNotification(string $message, array $playerIds, ?string $url = null, array $data = [], string $title = 'Notification'): bool
    {
        foreach ($playerIds as $playerId) {
            try {
                OneSignal::sendNotificationToUser(
                    $message,
                    $playerId,
                    $url,
                    [
                        'title' => $title,
                        'additional_data' => $data,
                        'priority' => 10
                    ],
                );

                Log::info('Notification sent successfully', [
                    'message' => $message,
                    'data' => $data,
                    'title' => $title,
                ]);
            } catch (\Exception $e) {
                Log::error('Error sending notification', ['message' => $e->getMessage(), 'playerId' => $playerId, 'data' => $data]);
            }
        }
        return true;

    }
}
