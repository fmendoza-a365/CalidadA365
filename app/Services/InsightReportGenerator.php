<?php

namespace App\Services;

use App\Support\AiSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Generates AI-powered insight reports from evaluation data.
 * Extracted from AIEvaluationService for single-responsibility.
 */
class InsightReportGenerator
{
    private AIEvaluationService $aiService;

    public function __construct(AIEvaluationService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Generate a grouped insight report from evaluations.
     */
    public function generate(Collection $evaluations, string $type = 'combined', array $reportSnapshot = []): array
    {
        $firstEval = $evaluations->first();
        $firstEval?->loadMissing('campaign.parent');
        $campaign = $firstEval?->campaign;
        $campaignName = $reportSnapshot['scope']['campaign_name'] ?? $campaign?->displayName() ?? 'Todas las campañas visibles';
        $campaignDescription = $campaign?->description ?: 'Análisis consolidado sobre evaluaciones reales del periodo seleccionado.';

        $qualityCriteria = [];
        foreach ($evaluations->pluck('formVersion')->filter()->unique('id') as $qualityForm) {
            foreach ($qualityForm->formAttributes as $attr) {
                foreach ($attr->subAttributes as $sub) {
                    $key = $attr->name.'|'.$sub->name;
                    $qualityCriteria[$key] = [
                        'category' => $attr->name,
                        'criterion' => $sub->name,
                        'is_critical' => $sub->is_critical,
                    ];
                }
            }
        }
        $qualityCriteria = array_values($qualityCriteria);
        $criteriaJson = json_encode($qualityCriteria, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $metricsJson = json_encode($reportSnapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $failedItems = [];
        foreach ($evaluations as $eval) {
            foreach ($eval->items as $item) {
                if ($item->status === 'non_compliant') {
                    $failedItems[] = [
                        'evaluation_id' => $eval->id,
                        'agent' => $eval->agent?->name,
                        'score' => $eval->percentage_score,
                        'category' => $item->subAttribute?->attribute?->name ?? 'Unknown',
                        'criteria' => $item->subAttribute?->name ?? 'Unknown',
                        'evidence' => $item->evidence_quote,
                        'notes' => $item->ai_notes,
                        'is_critical' => $item->subAttribute?->is_critical ?? false,
                    ];
                }
            }
        }

        if (count($failedItems) < 3) {
            return $this->simulateResponse($type, $reportSnapshot);
        }

        $sampleFailures = array_slice($failedItems, 0, 50);
        $failuresJson = json_encode($sampleFailures, JSON_UNESCAPED_UNICODE);

        $roleInstruction = match ($type) {
            'operational' => 'Eres un Gerente de Operaciones de Call Center. Tu objetivo es mejorar el desempeño de los agentes.',
            'strategic' => 'Eres un Consultor de Negocios para el Cliente Corporativo (la marca). Tu objetivo es detectar fricciones en el producto/proceso.',
            default => 'Eres un experto analista de calidad de Call Center.'
        };

        $focusInstruction = match ($type) {
            'operational' => 'Céntrate en: Errores de script, falta de empatía, muletillas, no seguir procedimientos, tiempos muertos.',
            'strategic' => 'Céntrate en: Quejas recurrentes sobre el producto, procesos burocráticos que molestan al cliente, confusión con facturación/promociones.',
            default => 'Analiza tanto el desempeño del agente como los problemas del proceso.'
        };

        $prompt = <<<PROMPT
{$roleInstruction}

**CONTEXTO DE LA CAMPAÑA:**
- Nombre / alcance: {$campaignName}
- Descripción: {$campaignDescription}

**MÉTRICAS REALES DEL PERIODO ANALIZADO:**
{$metricsJson}

**CRITERIOS DE LA FICHA DE CALIDAD:**
{$criteriaJson}

**DATOS DE FALLAS DETECTADAS:**
{$failuresJson}

{$focusInstruction}

**INSTRUCCIONES CRÍTICAS:**
1. TODO el análisis debe estar en ESPAÑOL
2. Basa tu análisis EXCLUSIVAMENTE en las evaluaciones, métricas reales y criterios de la ficha mostrados arriba
3. Relaciona cada hallazgo con criterios específicos de la ficha
4. Menciona qué criterios del alcance "{$campaignName}" se están incumpliendo
5. Sé ESPECÍFICO y ACCIONABLE

Genera un reporte EXHAUSTIVO en formato JSON (TODO EN ESPAÑOL):
{
    "executive_summary": "Resumen ejecutivo en Markdown. INCLUYE: contexto de la campaña, criterios más problemáticos, conclusión.",
    "operations_summary": "Resumen para operaciones: foco en agentes, supervisión, coaching, proceso y seguimiento.",
    "client_summary": "Resumen para cliente: foco ejecutivo, impacto en experiencia, riesgos y decisiones sin culpar agentes.",
    "improvement_opportunities": [
        {
            "category": "Nombre del criterio de la FICHA DE CALIDAD",
            "description": "Descripción del problema en este criterio",
            "affected_count": 5,
            "priority": "High|Medium|Low",
            "coaching_actions": "Acciones específicas para este criterio"
        }
    ],
    "deficiencies": [
        {
            "title": "Deficiencia (relacionada con criterios de la ficha)",
            "description": "Descripción del problema sistémico",
            "frequency": "Frecuente|Ocasional|Rara",
            "root_cause": "Causa raíz probable",
            "recommendation": "Acción correctiva específica"
        }
    ],
    "product_issues": [
        {
            "issue": "Problema del producto/servicio",
            "customer_impact": "Cómo afecta al cliente",
            "evidence": "Evidencia en transcripciones",
            "suggested_fix": "Solución propuesta"
        }
    ],
    "trends": {
        "overall_direction": "improving|declining|stable",
        "key_observations": "Observaciones sobre tendencias",
        "critical_changes": "Cambios críticos"
    },
    "recommendations": [
        {
            "priority": 1,
            "action": "Acción específica (relacionada con criterios de la ficha)",
            "expected_impact": "Impacto esperado en el puntaje",
            "responsible": "Operaciones|Producto|Negocio|Capacitación"
        }
    ],
    "presentation_slides": [
        {
            "title": "Título de slide",
            "bullets": ["Mensaje breve basado en datos", "Otro punto clave"],
            "speaker_note": "Nota corta para explicar en reunión"
        }
    ]
}

GENERA MÍNIMO: 3 improvement_opportunities, 2 deficiencies, 3 recommendations y 6 presentation_slides.
RECUERDA: TODO en español, basado en evaluaciones reales y ficha de calidad de "{$campaignName}".
PROMPT;

        try {
            $provider = $this->aiService->getProvider();
            $response = match ($provider) {
                'gemini', 'openai', 'claude' => $this->aiService->analyze($prompt),
                default => null,
            };

            // analyze() returns a string, parse it as JSON
            if (is_string($response)) {
                $parsed = json_decode($response, true);
                $response = is_array($parsed) ? $parsed : null;
            }
        } catch (\Throwable $exception) {
            Log::warning('Insight report AI generation failed; using evaluation snapshot fallback.', [
                'provider' => $this->aiService->getProvider(),
                'message' => $exception->getMessage(),
            ]);

            $response = null;
        }

        return array_replace_recursive(
            $this->simulateResponse($type, $reportSnapshot),
            is_array($response) ? $response : []
        );
    }

    /**
     * Basic insight response built from report snapshot data (no AI).
     */
    public function basicResponse(string $type, array $reportSnapshot): array
    {
        $scope = $reportSnapshot['scope'] ?? [];
        $metrics = $reportSnapshot['metrics'] ?? [];
        $topFailedCriteria = array_values($reportSnapshot['top_failed_criteria'] ?? []);
        $agentPerformance = array_values($reportSnapshot['agent_performance'] ?? []);
        $campaignName = $scope['campaign_name'] ?? 'Todas las campañas visibles';
        $totalEvaluations = (int) ($metrics['total_evaluations'] ?? 0);
        $averageScore = (float) ($metrics['average_score'] ?? 0);
        $complianceRate = (float) ($metrics['compliance_rate'] ?? 0);
        $criticalFailures = (int) ($metrics['critical_failures'] ?? 0);
        $mainCriteria = $topFailedCriteria[0]['criteria'] ?? 'sin criterio dominante';
        $mainCriteriaCount = (int) ($topFailedCriteria[0]['count'] ?? 0);
        $lowestAgent = $agentPerformance[0]['agent'] ?? 'sin asesor crítico';

        $priorityFromCount = fn (int $count, bool $critical = false): string => $critical || $count >= 5 ? 'High' : ($count >= 2 ? 'Medium' : 'Low');

        $opportunities = collect($topFailedCriteria)
            ->take(5)
            ->map(fn (array $criteria) => [
                'category' => $criteria['criteria'] ?? 'Criterio de calidad',
                'description' => 'Se detectaron incumplimientos recurrentes en '.$criteria['criteria'].' dentro del periodo analizado.',
                'affected_count' => (int) ($criteria['count'] ?? 0),
                'priority' => $priorityFromCount((int) ($criteria['count'] ?? 0), (bool) ($criteria['critical'] ?? false)),
                'coaching_actions' => 'Revisar casos reales del criterio, reforzar pauta de evaluación y ejecutar calibración con supervisores.',
            ])
            ->values()
            ->all();

        if (empty($opportunities)) {
            $opportunities[] = [
                'category' => 'Seguimiento preventivo',
                'description' => 'No se detectó una falla dominante, pero el periodo requiere seguimiento por variación de resultados.',
                'affected_count' => 0,
                'priority' => 'Low',
                'coaching_actions' => 'Mantener calibraciones y revisar muestras con puntajes bajo el objetivo.',
            ];
        }

        return [
            'executive_summary' => "### Resumen ejecutivo\n\nSe analizaron {$totalEvaluations} evaluaciones de {$campaignName}. El promedio fue {$averageScore}% y el cumplimiento fue {$complianceRate}%. Se detectaron {$criticalFailures} fallas críticas. El criterio con mayor recurrencia fue **{$mainCriteria}** con {$mainCriteriaCount} caso(s).",
            'operations_summary' => "Operaciones debe priorizar coaching sobre {$mainCriteria}, revisar a {$lowestAgent} y reforzar calibración con supervisores. La meta inmediata es reducir fallas críticas y elevar consistencia sobre el umbral de 80%.",
            'client_summary' => "El periodo muestra un nivel promedio de {$averageScore}% en calidad para {$campaignName}. Los principales riesgos se concentran en criterios operativos específicos y deben gestionarse con un plan de mejora medible.",
            'improvement_opportunities' => $opportunities,
            'deficiencies' => collect($topFailedCriteria)->take(3)->map(fn (array $criteria) => [
                'title' => $criteria['criteria'] ?? 'Criterio con desviación',
                'description' => 'El criterio aparece entre las principales causas de incumplimiento en el periodo.',
                'frequency' => ((int) ($criteria['count'] ?? 0)) >= 5 ? 'Frecuente' : 'Ocasional',
                'root_cause' => 'Posible brecha de ejecución, entendimiento del procedimiento o calibración del criterio.',
                'recommendation' => 'Revisar evidencias, alinear supervisores y medir recuperación en el siguiente corte.',
            ])->values()->all(),
            'product_issues' => [],
            'trends' => [
                'overall_direction' => 'stable',
                'key_observations' => 'La tendencia debe interpretarse con el detalle diario y la distribución de puntajes del reporte.',
                'critical_changes' => $criticalFailures > 0 ? 'Existen fallas críticas que requieren seguimiento.' : 'No se detectaron fallas críticas en el resumen consolidado.',
            ],
            'recommendations' => [
                [
                    'priority' => 1,
                    'action' => 'Ejecutar coaching focalizado sobre '.$mainCriteria,
                    'expected_impact' => 'Reducir reincidencia del criterio y mejorar el promedio de calidad.',
                    'responsible' => 'Operaciones',
                ],
                [
                    'priority' => 2,
                    'action' => 'Calibrar la pauta con supervisores y monitores',
                    'expected_impact' => 'Homologar criterios y reducir variabilidad entre evaluaciones.',
                    'responsible' => 'Calidad',
                ],
                [
                    'priority' => 3,
                    'action' => 'Revisar semanalmente agentes con menor promedio',
                    'expected_impact' => 'Detectar brechas individuales antes de que se acumulen fallas.',
                    'responsible' => 'Supervisión',
                ],
            ],
            'presentation_slides' => [
                [
                    'title' => 'Resultado general del periodo',
                    'bullets' => ["{$totalEvaluations} evaluaciones analizadas", "Promedio de calidad: {$averageScore}%", "Cumplimiento: {$complianceRate}%"],
                    'speaker_note' => 'Abrir con el alcance del análisis y los indicadores base.',
                ],
                [
                    'title' => 'Principal foco de mejora',
                    'bullets' => ["Criterio principal: {$mainCriteria}", "{$mainCriteriaCount} incumplimiento(s) detectado(s)", "Prioridad basada en recurrencia y criticidad"],
                    'speaker_note' => 'Explicar por qué este criterio debe priorizarse.',
                ],
                [
                    'title' => 'Riesgos para la operación',
                    'bullets' => ["Fallas críticas: {$criticalFailures}", 'Riesgo de variabilidad entre agentes', 'Necesidad de seguimiento semanal'],
                    'speaker_note' => 'Conectar fallas con acciones operativas concretas.',
                ],
                [
                    'title' => 'Plan de acción',
                    'bullets' => ['Coaching focalizado', 'Calibración de criterios', 'Seguimiento de asesores con menor promedio'],
                    'speaker_note' => 'Cerrar con responsables y próximo corte.',
                ],
            ],
        ];
    }

    /**
     * Simulated insight response (for testing or when insufficient data).
     */
    public function simulateResponse(string $type, array $reportSnapshot = []): array
    {
        if (! empty($reportSnapshot)) {
            return $this->basicResponse($type, $reportSnapshot);
        }

        return [
            'executive_summary' => "### Reporte simulado ({$type})\n\nEste es un análisis generado automáticamente para pruebas. En producción, esto usaría IA real para analizar tendencias y patrones en las evaluaciones.\n\n**Nota:** Configure un proveedor de IA (Gemini/OpenAI) para obtener insights reales.",
            'improvement_opportunities' => [
                [
                    'category' => 'Script Adherence',
                    'description' => 'Múltiples agentes omiten el saludo corporativo completo',
                    'affected_count' => 8,
                    'priority' => 'High',
                    'coaching_actions' => 'Reforzar roleplay de apertura en sesiones 1:1',
                ],
                [
                    'category' => 'Empathy',
                    'description' => 'Falta de validación emocional en quejas de clientes',
                    'affected_count' => 5,
                    'priority' => 'Medium',
                    'coaching_actions' => 'Capacitación en técnicas de escucha activa',
                ],
                [
                    'category' => 'Product Knowledge',
                    'description' => 'Confusión sobre términos de promociones vigentes',
                    'affected_count' => 3,
                    'priority' => 'Medium',
                    'coaching_actions' => 'Quiz semanal de actualizaciones de producto',
                ],
            ],
            'deficiencies' => [
                [
                    'title' => 'Script Desactualizado',
                    'description' => 'El script de apertura no refleja las promociones actuales',
                    'frequency' => 'Recurrente',
                    'root_cause' => 'Falta de comunicación entre Marketing y Operaciones',
                    'recommendation' => 'Establecer proceso de actualización quincenal de scripts',
                ],
                [
                    'title' => 'Tiempo de Espera Elevado',
                    'description' => 'Clientes reportan tiempos de espera prolongados al verificar información',
                    'frequency' => 'Frecuente',
                    'root_cause' => 'Sistema CRM lento o agentes no capacitados en atajos',
                    'recommendation' => 'Optimización técnica + capacitación en uso eficiente de herramientas',
                ],
            ],
            'product_issues' => [
                [
                    'issue' => 'Confusión en Facturación de Extras',
                    'customer_impact' => 'Clientes se sorprenden con cargos no explicados previamente',
                    'evidence' => 'Múltiples llamadas por consultas de facturación',
                    'suggested_fix' => 'Mejorar transparencia en documentación de cargos adicionales',
                ],
            ],
            'trends' => [
                'overall_direction' => 'stable',
                'key_observations' => 'El desempeño se mantiene estable sin cambios significativos',
                'critical_changes' => 'Ninguno detectado en el periodo analizado',
            ],
            'recommendations' => [
                [
                    'priority' => 1,
                    'action' => 'Actualizar script de apertura con promociones vigentes',
                    'expected_impact' => 'Reducción del 30% en consultas sobre promociones',
                    'responsible' => 'Operaciones + Marketing',
                ],
                [
                    'priority' => 2,
                    'action' => 'Implementar sesiones de coaching 1:1 para agentes de bajo desempeño',
                    'expected_impact' => 'Mejora de 10-15 puntos en puntaje promedio',
                    'responsible' => 'Supervisores',
                ],
                [
                    'priority' => 3,
                    'action' => 'Revisión de UX en facturación digital',
                    'expected_impact' => 'Reducción de llamadas de consulta',
                    'responsible' => 'Producto/Negocio',
                ],
            ],
        ];
    }
}
