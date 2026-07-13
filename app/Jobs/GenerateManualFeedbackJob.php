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
            return "- {$item->subAttribute->name}: No cumple. Evidencia: ".($item->evidence_quote ?: 'Sin evidencia específica.');
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

            if (! is_string($response) || empty($response)) {
                Log::warning("GenerateManualFeedbackJob: AI retornó vacío para evaluación #{$evaluation->id}");
                $this->storeFallbackFeedback($evaluation, $items, $failedItems, $passedItems, 'empty_ai_response');

                return;
            }

            // Extract JSON from response (may be wrapped in markdown or have extra text)
            $jsonString = $response;

            // Try markdown code block first
            if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $response, $matches)) {
                $jsonString = $matches[1];
            } else {
                // Find first { to last } boundary
                $start = strpos($response, '{');
                $end = strrpos($response, '}');
                if ($start !== false && $end !== false && $end > $start) {
                    $jsonString = substr($response, $start, $end - $start + 1);
                }
            }

            // Clean control characters that break JSON
            $jsonString = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $jsonString);

            $feedback = json_decode($jsonString, true);

            if (is_array($feedback) && isset($feedback['performanceSummary'])) {
                $this->storeFeedback($evaluation, $feedback, 'ai');

                return;
            }

            Log::warning("GenerateManualFeedbackJob: AI no retornó feedback válido para evaluación #{$evaluation->id}", [
                'json_error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 300),
            ]);
            $this->storeFallbackFeedback($evaluation, $items, $failedItems, $passedItems, 'invalid_ai_json');
        } catch (Throwable $e) {
            Log::error("GenerateManualFeedbackJob: error para evaluación #{$evaluation->id}: ".$e->getMessage());
            $this->storeFallbackFeedback($evaluation, $items, $failedItems, $passedItems, 'ai_exception');
        }
    }

    private function storeFeedback(Evaluation $evaluation, array $feedback, string $source): void
    {
        $evaluation->update(['ai_feedback' => $feedback]);
        Log::info("GenerateManualFeedbackJob: feedback {$source} generado para evaluación #{$evaluation->id}");

        if (config('ai.feedback_tts.enabled')) {
            $evaluation->update(['feedback_audio_status' => 'pending']);
            GenerateFeedbackAudioJob::dispatch($evaluation->id);
        }
    }

    private function storeFallbackFeedback(Evaluation $evaluation, $items, $failedItems, $passedItems, string $reason): void
    {
        if ($items->isEmpty()) {
            return;
        }

        Log::warning("GenerateManualFeedbackJob: usando feedback fallback para evaluación #{$evaluation->id}", [
            'reason' => $reason,
        ]);

        $this->storeFeedback($evaluation, $this->fallbackFeedback($evaluation, $failedItems, $passedItems), 'fallback');
    }

    private function fallbackFeedback(Evaluation $evaluation, $failedItems, $passedItems): array
    {
        $failedLabels = $failedItems
            ->map(fn ($item) => $item->subAttribute?->name)
            ->filter()
            ->take(5)
            ->implode(', ');
        $passedLabels = $passedItems
            ->map(fn ($item) => $item->subAttribute?->name)
            ->filter()
            ->take(5)
            ->implode(', ');
        $score = number_format((float) $evaluation->percentage_score, 2, '.', '');

        return [
            'performanceSummary' => "La evaluación manual cerró con {$score}%. Cumplió {$passedItems->count()} criterios y no cumplió {$failedItems->count()}.",
            'productKnowledge' => $failedLabels !== ''
                ? "Reforzar los criterios observados: {$failedLabels}."
                : 'No se registraron brechas relevantes de conocimiento del producto en los criterios revisados.',
            'emotionalHandlingAndEmpathy' => 'Mantener una comunicación clara, empática y orientada a resolver la necesidad del cliente durante toda la interacción.',
            'strengths' => $passedLabels !== ''
                ? "Fortalezas observadas: {$passedLabels}."
                : 'La evaluación no registró criterios cumplidos suficientes para destacar fortalezas específicas.',
            'improvementOpportunities' => $failedLabels !== ''
                ? "Priorizar la mejora en: {$failedLabels}. Revisar el caso con el supervisor y practicar el flujo esperado."
                : 'Continuar reforzando consistencia y cierre de la atención según la ficha de calidad.',
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error("GenerateManualFeedbackJob failed for evaluation {$this->evaluationId}: ".$exception->getMessage());
    }
}
