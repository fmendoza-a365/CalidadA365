<?php

namespace App\Jobs;

use App\Exceptions\PermanentAiProviderException;
use App\Exceptions\TransientAiProviderException;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Services\AIEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 60;

    public $maxExceptions = 3;

    public $timeout = 720;

    public function __construct(public int $interactionId) {}

    public function middleware(): array
    {
        return [new RateLimited('ai-provider')];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(max(1, (int) config('queue.ai.retry_window_hours', 12)));
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }

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

        $metadata = $interaction->metadata ?? [];
        $metadata['scoring_started_at'] = now()->toIso8601String();

        $interaction->update([
            'status' => 'scoring',
            'metadata' => $metadata,
        ]);

        $fromStatus = $existingAiEvaluation->status;
        $existingAiEvaluation->update(['status' => Evaluation::STATUS_AI_PROCESSING]);
        $existingAiEvaluation->recordAuditEvent('ai_processing_started', null, [
            'source' => 'score_job',
        ], $fromStatus, Evaluation::STATUS_AI_PROCESSING);

        try {
            $evaluation = $aiService->evaluateInteraction($interaction, $existingAiEvaluation);

            if ($evaluation) {
                $metadata = $interaction->metadata ?? [];
                $metadata['scoring_completed_at'] = now()->toIso8601String();

                $interaction->update([
                    'status' => 'scored',
                    'metadata' => $metadata,
                ]);
                Log::info("Evaluation completed for interaction {$this->interactionId}");
            } else {
                if ($this->shouldRetryNullResponse()) {
                    $this->scheduleAiRetry(
                        $interaction,
                        $existingAiEvaluation,
                        'service_returned_null',
                        'La IA devolvió una respuesta vacía o inválida. Se reintentará automáticamente.'
                    );

                    return;
                }

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
        } catch (TransientAiProviderException $e) {
            $this->scheduleAiRetry(
                $interaction,
                $existingAiEvaluation,
                'provider_transient',
                'El proveedor de IA está ocupado o limitó temporalmente la solicitud. Se reintentará automáticamente.',
                $e
            );

            return;
        } catch (PermanentAiProviderException $e) {
            $interaction->update(['status' => 'uploaded']);
            $fromStatus = $existingAiEvaluation->status;
            $existingAiEvaluation->update([
                'status' => Evaluation::STATUS_AI_FAILED,
                'ai_summary' => 'La evaluación IA no pudo completarse por configuración del proveedor: '.$e->getMessage(),
            ]);
            $existingAiEvaluation->recordAuditEvent('ai_failed', null, [
                'source' => 'provider_permanent',
                'exception_class' => get_class($e),
            ], $fromStatus, Evaluation::STATUS_AI_FAILED);

            Log::error("Permanent AI provider failure for interaction {$this->interactionId}: ".$e->getMessage());
            $this->fail($e);

            return;
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

    private function shouldRetryNullResponse(): bool
    {
        return $this->attempts() <= max(0, (int) config('queue.ai.null_response_retries', 2));
    }

    private function scheduleAiRetry(
        Interaction $interaction,
        Evaluation $evaluation,
        string $source,
        string $summary,
        ?\Throwable $exception = null
    ): void {
        $delay = $this->transientReleaseDelay();
        $interaction->update(['status' => 'queued']);
        $fromStatus = $evaluation->status;
        $evaluation->update([
            'status' => Evaluation::STATUS_PENDING_AI,
            'ai_summary' => $summary,
        ]);
        $evaluation->recordAuditEvent('ai_retry_scheduled', null, [
            'source' => $source,
            'retry_in_seconds' => $delay,
            'attempt' => $this->attempts(),
            'exception_class' => $exception ? get_class($exception) : null,
        ], $fromStatus, Evaluation::STATUS_PENDING_AI);

        $detail = $exception ? ': '.$exception->getMessage() : '';
        Log::warning("ScoreTranscriptJob: Retry scheduled for interaction {$this->interactionId} in {$delay}s{$detail}");
        $this->release($delay);
    }

    private function transientReleaseDelay(): int
    {
        $baseDelay = max(30, (int) config('queue.ai.transient_release_seconds', 120));
        $maxDelay = max($baseDelay, (int) config('queue.ai.max_transient_release_seconds', 900));

        return min($maxDelay, $baseDelay * max(1, $this->attempts()));
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
