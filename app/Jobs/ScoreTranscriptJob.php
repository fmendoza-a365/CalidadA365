<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Models\Interaction;
use App\Services\AIEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 720;

    public function __construct(public int $interactionId) {}

    public function handle(AIEvaluationService $aiService): void
    {
        $interaction = Interaction::find($this->interactionId);

        if (! $interaction) {
            Log::error("Interaction {$this->interactionId} not found");

            return;
        }

        $existingAiEvaluation = $interaction->aiEvaluation()->first();

        if ($interaction->manualEvaluation()->exists()) {
            Log::info("Interaction {$this->interactionId} already has a manual evaluation");

            return;
        }

        if (
            $existingAiEvaluation
            && ! in_array($existingAiEvaluation->status, [
                Evaluation::STATUS_PENDING_AI,
                Evaluation::STATUS_AI_PROCESSING,
                Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
                Evaluation::STATUS_AI_FAILED,
            ], true)
        ) {
            Log::info("Interaction {$this->interactionId} already has an AI evaluation");

            return;
        }

        if (! $existingAiEvaluation) {
            $formVersion = $interaction->scorableFormVersion();
            if (! $formVersion) {
                Log::error("Interaction {$this->interactionId} has no published quality form to score");

                return;
            }

            $existingAiEvaluation = Evaluation::createPendingAiForInteraction($interaction, $formVersion, null, [
                'source' => 'score_job',
            ]);
        }

        $interaction->update(['status' => 'scoring']);

        $fromStatus = $existingAiEvaluation->status;
        $existingAiEvaluation->update(['status' => Evaluation::STATUS_AI_PROCESSING]);
        $existingAiEvaluation->recordAuditEvent('ai_processing_started', null, [
            'source' => 'score_job',
        ], $fromStatus, Evaluation::STATUS_AI_PROCESSING);

        try {
            $evaluation = $aiService->evaluateInteraction($interaction, $existingAiEvaluation);

            if ($evaluation) {
                $interaction->update(['status' => 'scored']);
                Log::info("Evaluation completed for interaction {$this->interactionId}");
            } else {
                $interaction->update(['status' => 'uploaded']);
                $fromStatus = $existingAiEvaluation->status;
                $existingAiEvaluation->update([
                    'status' => Evaluation::STATUS_AI_FAILED,
                    'ai_summary' => 'La evaluación IA no pudo completarse. Revise la API key, el modelo configurado, la ficha de calidad y los logs del sistema.',
                ]);
                $existingAiEvaluation->recordAuditEvent('ai_failed', null, [
                    'source' => 'service_returned_null',
                ], $fromStatus, Evaluation::STATUS_AI_FAILED);
                Log::error("Failed to evaluate interaction {$this->interactionId}");
            }
        } catch (\Exception $e) {
            $interaction->update(['status' => 'uploaded']);
            $fromStatus = $existingAiEvaluation->status;
            $existingAiEvaluation->update([
                'status' => Evaluation::STATUS_AI_FAILED,
                'ai_summary' => 'La evaluación IA falló: '.$e->getMessage(),
            ]);
            $existingAiEvaluation->recordAuditEvent('ai_failed', null, [
                'source' => 'job_exception',
                'exception_class' => get_class($e),
            ], $fromStatus, Evaluation::STATUS_AI_FAILED);
            Log::error("Error evaluating interaction {$this->interactionId}: ".$e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $interaction = Interaction::find($this->interactionId);
        if ($interaction) {
            $interaction->update(['status' => 'uploaded']);
            $evaluation = $interaction->aiEvaluation()->first();
            if ($evaluation) {
                $fromStatus = $evaluation->status;
                $evaluation->update([
                    'status' => Evaluation::STATUS_AI_FAILED,
                    'ai_summary' => 'La evaluación IA falló en cola: '.$exception->getMessage(),
                ]);
                $evaluation->recordAuditEvent('ai_failed', null, [
                    'source' => 'queue_failed',
                    'exception_class' => get_class($exception),
                ], $fromStatus, Evaluation::STATUS_AI_FAILED);
            }
        }

        Log::error("ScoreTranscriptJob failed for interaction {$this->interactionId}: ".$exception->getMessage());
    }
}
