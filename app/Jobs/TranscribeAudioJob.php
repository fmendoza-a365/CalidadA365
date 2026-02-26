<?php

namespace App\Jobs;

use App\Models\Interaction;
use App\Services\AudioTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600; // 10 minutes max for large audio files

    public function __construct(public int $interactionId)
    {
    }

    public function handle(AudioTranscriptionService $transcriptionService): void
    {
        $interaction = Interaction::find($this->interactionId);

        if (!$interaction) {
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

        $interaction->update(['transcription_status' => 'processing']);

        try {
            $result = $transcriptionService->transcribe($interaction->file_path);

            $updateData = [
                'transcript_text' => $result['transcript'],
                'transcription_status' => 'completed',
                'status' => 'uploaded',
            ];

            // Store sentiment analysis in metadata
            if (!empty($result['sentiment'])) {
                $metadata = $interaction->metadata ?? [];
                $metadata['sentiment'] = $result['sentiment'];
                $updateData['metadata'] = $metadata;
            }

            $interaction->update($updateData);

            Log::info("TranscribeAudioJob: Transcription completed for interaction {$this->interactionId}");

        } catch (\Exception $e) {
            $interaction->update([
                'transcription_status' => 'failed',
                'status' => 'uploaded',
            ]);

            Log::error("TranscribeAudioJob: Failed for interaction {$this->interactionId}: " . $e->getMessage());
            throw $e;
        }
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

        Log::error("TranscribeAudioJob: Permanently failed for interaction {$this->interactionId}: " . $exception->getMessage());
    }
}
