<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EvaluationReviewed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $evaluation,
        public $agent,
        public string $type // 'reviewed' or 'commitment'
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
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
        $actionWord = $this->type === 'commitment' ? 'registró un compromiso' : 'confirmó la lectura';
        $agentName = $this->agent->name;

        return [
            'title' => 'Evaluación revisada',
            'body' => "El asesor {$agentName} ha revisado la evaluación y {$actionWord}.",
            'data' => [
                'type' => 'evaluation_reviewed',
                'evaluation_id' => $this->evaluation->id,
                'agent_id' => $this->agent->id,
                'response_type' => $this->type,
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
        $agentName = $this->agent->full_name;
        $actionWord = $this->type === 'commitment' ? 'registró un compromiso' : 'confirmó la lectura';
        
        return \NotificationChannels\Telegram\TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("🔔 *Evaluación QA Revisada*\n\n" .
                "El asesor *{$agentName}* ha revisado la evaluación y *{$actionWord}*.\n\n" .
                "Revisa la respuesta haciendo clic abajo:")
            ->button('Ver Evaluación', $evaluationUrl);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $agentName = $this->agent->full_name;
        $actionWord = $this->type === 'commitment' ? 'registró un compromiso' : 'confirmó la lectura';

        return [
            'title' => 'Evaluación revisada',
            'message' => "El asesor {$agentName} ha revisado la evaluación y {$actionWord}.",
            'action_url' => route('evaluations.show', $this->evaluation),
            'icon' => 'clipboard-check',
            'type' => 'info',
        ];
    }
}
