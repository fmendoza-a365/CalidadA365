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
        $channels = ['database'];

        if ($notifiable->telegram_chat_id) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        $evaluationUrl = route('evaluations.show', $this->evaluation->id);
        $campaignName = $this->evaluation->campaign->name ?? 'N/A';
        $score = $this->evaluation->percentage_score;
        $statusIcon = $score >= 90 ? '🟢' : ($score >= 70 ? '🟡' : '🔴');

        return \NotificationChannels\Telegram\TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("{$statusIcon} *Nueva Evaluación QA Completada*\n\n" .
                "*Campaña:* {$campaignName}\n" .
                "*Puntaje:* {$score}%\n\n" .
                "Revisa el detalle y feedback de la IA haciendo clic abajo:")
            ->button('Ver Evaluación', $evaluationUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nueva Evaluación Completada',
            'message' => "Se ha completado una evaluación para la campaña {$this->evaluation->campaign->name}. Puntaje: {$this->evaluation->percentage_score}%",
            'action_url' => route('evaluations.show', $this->evaluation),
            'icon' => 'clipboard-check',
            'type' => 'success',
        ];
    }
}
