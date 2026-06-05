<?php

namespace Tests\Unit;

use App\Models\Evaluation;
use Tests\TestCase;

class EvaluationStructuredFeedbackTest extends TestCase
{
    public function test_it_splits_legacy_ai_summary_into_expected_feedback_sections(): void
    {
        $evaluation = new Evaluation([
            'ai_summary' => 'Resumen del desempeño: Gestion correcta. Conocimiento del producto: Explico el plan. Manejo de emociones y empatía: Respondio con calma. Fortalezas: Saludo claro. Oportunidades de mejora: Profundizar objeciones.',
        ]);

        $feedback = collect($evaluation->structuredAiFeedback())->keyBy('key');

        $this->assertStringContainsString('Gestion correcta', $feedback['performanceSummary']['content']);
        $this->assertStringContainsString('Explico el plan', $feedback['productKnowledge']['content']);
        $this->assertStringContainsString('Respondio con calma', $feedback['emotionalHandlingAndEmpathy']['content']);
        $this->assertStringContainsString('Saludo claro', $feedback['strengths']['content']);
        $this->assertStringContainsString('Profundizar objeciones', $feedback['improvementOpportunities']['content']);
    }

    public function test_structured_feedback_takes_precedence_over_legacy_summary(): void
    {
        $evaluation = new Evaluation([
            'ai_summary' => 'Resumen: texto anterior.',
            'ai_feedback' => [
                'performanceSummary' => 'Texto estructurado.',
                'productKnowledge' => 'Producto estructurado.',
            ],
        ]);

        $feedback = collect($evaluation->structuredAiFeedback())->keyBy('key');

        $this->assertSame('Texto estructurado.', $feedback['performanceSummary']['content']);
        $this->assertSame('Producto estructurado.', $feedback['productKnowledge']['content']);
    }
}
