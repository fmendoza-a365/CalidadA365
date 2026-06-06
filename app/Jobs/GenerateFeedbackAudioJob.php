<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Services\FeedbackAudioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFeedbackAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 180;

    public function __construct(public int $evaluationId) {}

    public function handle(FeedbackAudioService $feedbackAudio): void
    {
        if (! $feedbackAudio->isEnabled()) {
            return;
        }

        $evaluation = Evaluation::find($this->evaluationId);
        if (! $evaluation) {
            return;
        }

        $evaluation->update(['feedback_audio_status' => 'processing']);

        try {
            $result = $feedbackAudio->generate($evaluation);

            $evaluation->update([
                'feedback_audio_path' => $result['path'],
                'feedback_audio_disk' => $result['disk'],
                'feedback_audio_generated_at' => now(),
                'feedback_audio_status' => 'ready',
            ]);

            $evaluation->recordAuditEvent('feedback_audio_generated', null, [
                'disk' => $result['disk'],
                'path' => $result['path'],
                'bytes' => $result['bytes'],
            ]);
        } catch (Throwable $exception) {
            $evaluation->update(['feedback_audio_status' => 'failed']);
            $evaluation->recordAuditEvent('feedback_audio_failed', null, [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            Log::warning("GenerateFeedbackAudioJob failed for evaluation {$this->evaluationId}: ".$exception->getMessage());
        }
    }
}
