<?php

namespace App\Observers;

use App\Models\Evaluation;
use App\Notifications\DashboardRefreshNotification;
use Illuminate\Support\Facades\Log;

class EvaluationObserver
{
    public function updated(Evaluation $evaluation): void
    {
        if (! $evaluation->isDirty('status') && ! $evaluation->isDirty('feedback_audio_status')) {
            return;
        }

        $agent = $evaluation->agent;
        if (! $agent) {
            return;
        }

        $reason = match (true) {
            $evaluation->isDirty('status') && $evaluation->status === Evaluation::STATUS_PUBLISHED_TO_AGENT => 'evaluation_published',
            $evaluation->isDirty('status') && $evaluation->status === Evaluation::STATUS_AGENT_DISPUTED => 'evaluation_disputed',
            $evaluation->isDirty('status') && $evaluation->status === Evaluation::STATUS_CLOSED => 'evaluation_closed',
            $evaluation->isDirty('feedback_audio_status') && $evaluation->feedback_audio_status === 'ready' => 'feedback_audio_ready',
            default => null,
        };

        if ($reason === null) {
            return;
        }

        try {
            $agent->notify(new DashboardRefreshNotification($reason, $evaluation->id));
        } catch (\Throwable $e) {
            Log::warning("EvaluationObserver: failed to send dashboard refresh for evaluation #{$evaluation->id}: " . $e->getMessage());
        }
    }
}
