<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Services\AIEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateManualFeedbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 180;

    public function __construct(public int $evaluationId) {}

    public function handle(AIEvaluationService $aiService): void
    {
        $evaluation = Evaluation::find($this->evaluationId);

        if (! $evaluation || $evaluation->type !== 'manual') {
            return;
        }

        $interaction = $evaluation->interaction;
        if (! $interaction || blank($interaction->transcript_text)) {
            return;
        }

        $items = $evaluation->items()->with('subAttribute.attribute')->get();
        $failedItems = $items->where('status', 'non_compliant');
        $passedItems = $items->where('status', 'compliant');

        // Build a detailed prompt for the AI
        $criteriaSummary = $items->map(function ($item) {
            $status = match ($item->status) {
                'compliant' => 'Cumple',
                'non_compliant' => 'No cumple',
                default => 'No encontrado',
            };
            $note = filled($item->ai_notes) ? " Nota: {$item->ai_notes}" : '';
            return "- {$item->subAttribute->name} ({$item->subAttribute->attribute->name}): {$status}{$note}";
        })->implode("\n");

        $failedDetail = $failedItems->map(function ($item) {
            return "- {$item->subAttribute->name}: No cumple. Evidencia: " . ($item->evidence_quote ?: 'Sin evidencia específica.');
        })->implode("\n");

        $prompt = <<<PROMPT
Eres un analista de calidad de call centers. Un monitor humano corrigió una evaluación de una llamada.

**RESULTADO DE LA CORRECCIÓN:**
- Puntaje final: {$evaluation->percentage_score}%
- Criterios que cumplen: {$passedItems->count()}
- Criterios que no cumplen: {$failedItems->count()}

**CRITERIOS EVALUADOS:**
{$criteriaSummary}

**CRITERIOS FALLIDOS CON EVIDENCIA:**
{$failedDetail}

**TRANSCRIPCIÓN DE LA LLAMADA:**
{$interaction->transcript_text}

**INSTRUCCIONES:**
Genera un feedback profesional en ESPAÑOL con 5 secciones. Cada sección debe mencionar criterios específicos y citar evidencia de la transcripción cuando sea posible.

Responde SOLO con JSON válido:
{
    "performanceSummary": "Resumen del desempeño mencionando los criterios más relevantes con evidencia. Máximo 400 caracteres.",
    "productKnowledge": "Análisis de precisión y dominio del producto/servicio. Máximo 300 caracteres.",
    "emotionalHandlingAndEmpathy": "Análisis de tono, empatía y manejo emocional con ejemplos. Máximo 300 caracteres.",
    "strengths": "Las fortalezas más destacadas con criterio específico y evidencia. Máximo 250 caracteres.",
    "improvementOpportunities": "Los errores más importantes con: criterio fallido, qué pasó, qué debió hacerse. Máximo 400 caracteres."
}

REGLA: Todo el texto DEBE estar en ESPAÑOL. No uses Markdown dentro de los valores JSON.
PROMPT;

        try {
            $response = $aiService->analyze($prompt);

            if (is_string($response)) {
                $feedback = json_decode($response, true);

                if (is_array($feedback) && isset($feedback['performanceSummary'])) {
                    $evaluation->update(['ai_feedback' => $feedback]);
                    Log::info("GenerateManualFeedbackJob: feedback AI generado para evaluación #{$evaluation->id}");

                    // Dispatch audio generation
                    if (config('ai.feedback_tts.enabled')) {
                        $evaluation->update(['feedback_audio_status' => 'pending']);
                        GenerateFeedbackAudioJob::dispatch($evaluation->id);
                    }

                    return;
                }
            }

            Log::warning("GenerateManualFeedbackJob: AI no retornó feedback válido para evaluación #{$evaluation->id}");
        } catch (Throwable $e) {
            Log::error("GenerateManualFeedbackJob: error para evaluación #{$evaluation->id}: " . $e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("GenerateManualFeedbackJob failed for evaluation {$this->evaluationId}: " . $exception->getMessage());
    }
}
