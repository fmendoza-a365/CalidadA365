<?php

namespace App\Jobs;

use App\Models\Interaction;
use App\Models\Evaluation;
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

        $existingAiEvaluation = $interaction->aiEvaluation()->first();

        if ($existingAiEvaluation && $existingAiEvaluation->status !== Evaluation::STATUS_AI_REANALYSIS_REQUESTED) {
            Log::info("Interaction {$this->interactionId} already has an AI evaluation");
            return;
        }

        $interaction->update(['status' => 'scoring']);

        if ($existingAiEvaluation) {
            $existingAiEvaluation->update(['status' => Evaluation::STATUS_AI_PROCESSING]);
        }

        try {
            $evaluation = $aiService->evaluateInteraction($interaction, $existingAiEvaluation);
            
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
