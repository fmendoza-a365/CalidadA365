<?php

namespace App\Jobs;

use App\Exceptions\PermanentAiProviderException;
use App\Exceptions\TransientAiProviderException;
use App\Models\Interaction;
use App\Services\AudioTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 60;

    public $maxExceptions = 3;

    public $timeout = 600; // 10 minutes max for large audio files

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

    public function handle(AudioTranscriptionService $transcriptionService): void
    {
        $interaction = Interaction::find($this->interactionId);

        if (! $interaction) {
            Log::error("TranscribeAudioJob: Interaction {$this->interactionId} not found");

            return;
        }

        if ($interaction->source_type !== 'audio') {
            Log::info("TranscribeAudioJob: Interaction {$this->interactionId} is not an audio file, skipping");

            return;
        }

        if ($interaction->transcription_status === 'completed') {
            Log::info("TranscribeAudioJob: Interaction {$this->interactionId} already transcribed");

            return;
        }

        // Idempotency lock: prevent two workers from processing the same interaction
        $lock = Cache::lock("transcribe:{$this->interactionId}", 600);

        if (! $lock->get()) {
            Log::info("TranscribeAudioJob: Interaction {$this->interactionId} is already being processed by another worker");
            $this->release(30);

            return;
        }

        try {
            $interaction->refresh();

            if ($interaction->transcription_status === 'completed') {
                Log::info("TranscribeAudioJob: Interaction {$this->interactionId} already transcribed (after lock)");

                return;
            }

            $metadata = $interaction->metadata ?? [];
            $metadata['transcription_started_at'] = now()->toIso8601String();

            $interaction->update([
                'transcription_status' => 'processing',
                'metadata' => $metadata,
            ]);

            $this->doTranscribe($interaction, $transcriptionService);
        } finally {
            optional($lock)->release();
        }
    }

    private function doTranscribe(Interaction $interaction, AudioTranscriptionService $transcriptionService): void
    {

        try {
            $result = $transcriptionService->transcribe($interaction->file_path);

            $updateData = [
                'transcript_text' => $result['transcript'],
                'transcription_status' => 'completed',
                'status' => 'uploaded',
            ];

            if (! empty($result['duration_seconds'])) {
                $updateData['audio_duration'] = (int) $result['duration_seconds'];
            }

            // Store audio analysis in metadata so playback, emotion review, and QA scoring use the same source.
            if (! empty($result['sentiment'])
                || ! empty($result['sentiment_segments'])
                || ! empty($result['acoustic_analysis'])
                || ! empty($result['quality_signals'])) {
                $metadata = $interaction->metadata ?? [];

                foreach (['sentiment', 'sentiment_segments', 'acoustic_analysis', 'quality_signals'] as $key) {
                    if (! empty($result[$key])) {
                        $metadata[$key] = $result[$key];
                    }
                }

                $metadata['audio_analysis_version'] = 2;
                $updateData['metadata'] = $metadata;
            }

            $metadata = $updateData['metadata'] ?? ($interaction->metadata ?? []);
            $metadata['transcription_completed_at'] = now()->toIso8601String();
            $updateData['metadata'] = $metadata;

            $interaction->update($updateData);

            if (! $interaction->aiEvaluation()->exists() && $interaction->hasScorableQualityForm()) {
                $interaction->update(['status' => 'queued']);
                ScoreTranscriptJob::dispatch($interaction->id)->onQueue('ai-scoring');
            }

            Log::info("TranscribeAudioJob: Transcription completed for interaction {$this->interactionId}");

        } catch (TransientAiProviderException $e) {
            $delay = $this->transientReleaseDelay();
            $interaction->update([
                'transcription_status' => 'pending',
                'status' => 'uploaded',
            ]);

            Log::warning("TranscribeAudioJob: Transient provider issue for interaction {$this->interactionId}; retrying in {$delay}s: ".$e->getMessage());
            $this->release($delay);

            return;
        } catch (PermanentAiProviderException $e) {
            $interaction->update([
                'transcription_status' => 'failed',
                'status' => 'uploaded',
            ]);

            Log::error("TranscribeAudioJob: Permanent provider failure for interaction {$this->interactionId}: ".$e->getMessage());
            $this->fail($e);

            return;
        } catch (\Exception $e) {
            Log::error("TranscribeAudioJob: Attempt {$this->attempts()} failed for interaction {$this->interactionId}: ".$e->getMessage());
            throw $e;
        }
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
            $interaction->update([
                'transcription_status' => 'failed',
                'status' => 'uploaded',
            ]);
        }

        Log::error("TranscribeAudioJob: Permanently failed for interaction {$this->interactionId}: ".$exception->getMessage());
    }
}
