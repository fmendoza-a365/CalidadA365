<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationCompleted extends Notification
{
    use Queueable;

    public function __construct(public $evaluation)
    {
        //
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', \App\Channels\FcmChannel::class];

        if ($notifiable->telegram_chat_id) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): array
    {
        $this->evaluation->loadMissing('campaign.parent');
        $campaignName = $this->evaluation->campaign?->displayName() ?? 'N/A';

        return [
            'title' => 'Nueva evaluación disponible',
            'body' => 'Tienes una nueva evaluación de calidad pendiente de revisión.',
            'data' => [
                'type' => 'new_evaluation',
                'evaluation_id' => $this->evaluation->id,
                'campaign' => $campaignName,
                'score' => $this->evaluation->percentage_score,
                'deep_link' => "qa365://evaluations/{$this->evaluation->id}",
            ],
        ];
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        $evaluationUrl = route('evaluations.show', $this->evaluation->id);
        $this->evaluation->loadMissing('campaign.parent');
        $campaignName = $this->evaluation->campaign?->displayName() ?? 'N/A';
        $score = $this->evaluation->percentage_score;
        $statusIcon = $score >= 90 ? '🟢' : ($score >= 70 ? '🟡' : '🔴');

        return \NotificationChannels\Telegram\TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("{$statusIcon} *Nueva Evaluación QA Publicada*\n\n" .
                "*Campaña:* {$campaignName}\n" .
                "*Puntaje:* {$score}%\n\n" .
                "Revisa el detalle, feedback y compromiso haciendo clic abajo:")
            ->button('Ver Evaluación', $evaluationUrl);
    }

    public function toArray(object $notifiable): array
    {
        $this->evaluation->loadMissing('campaign.parent');
        $campaignName = $this->evaluation->campaign?->displayName() ?? 'N/A';

        return [
            'title' => 'Nueva Evaluación Publicada',
            'message' => "Se ha publicado una evaluación para la campaña {$campaignName}. Puntaje: {$this->evaluation->percentage_score}%",
            'action_url' => route('evaluations.show', $this->evaluation),
            'icon' => 'clipboard-check',
            'type' => 'success',
        ];
    }
}
