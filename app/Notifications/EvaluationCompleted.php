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
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
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
