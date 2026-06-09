<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DashboardRefreshNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $reason,
        private ?int $evaluationId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): ?array
    {
        return [
            'title' => 'dashboard_refresh',
            'body' => $this->reason,
            'data' => [
                'type' => 'dashboard_refresh',
                'reason' => $this->reason,
                'evaluation_id' => (string) ($this->evaluationId ?? ''),
            ],
        ];
    }
}
