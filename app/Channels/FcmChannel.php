<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class FcmChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notifiable, 'mobileDevices')) {
            return;
        }

        $tokens = $notifiable->mobileDevices()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return;
        }

        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $fcmData = $notification->toFcm($notifiable);
        if (!$fcmData) {
            return;
        }

        foreach ($tokens as $token) {
            \App\Services\FcmService::sendPushNotification($token, $fcmData);
        }
    }
}
