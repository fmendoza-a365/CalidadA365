<?php

namespace App\Jobs;

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
    public $timeout = 300;

    public function __construct(public int $interactionId)
    {
    }

    public function handle(AIEvaluationService $aiService): void
    {
        $interaction = Interaction::find($this->interactionId);
        
        if (!$interaction) {
            Log::error("Interaction {$this->interactionId} not found");
            return;
        }

        // Si ya tiene evaluación, omitir
        if ($interaction->evaluation) {
            Log::info("Interaction {$this->interactionId} already has evaluation");
            return;
        }

        $interaction->update(['status' => 'scoring']);

        try {
            $evaluation = $aiService->evaluateInteraction($interaction);
            
            if ($evaluation) {
                $interaction->update(['status' => 'scored']);
                Log::info("Evaluation completed for interaction {$this->interactionId}");
            } else {
                $interaction->update(['status' => 'error']);
                Log::error("Failed to evaluate interaction {$this->interactionId}");
            }
        } catch (\Exception $e) {
            $interaction->update(['status' => 'error']);
            Log::error("Error evaluating interaction {$this->interactionId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $interaction = Interaction::find($this->interactionId);
        if ($interaction) {
            $interaction->update(['status' => 'error']);
        }
        
        Log::error("ScoreTranscriptJob failed for interaction {$this->interactionId}: " . $exception->getMessage());
    }
}
