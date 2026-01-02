<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\QualityFormVersion;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIEvaluationService
{
    protected string $provider;
    protected array $config;

    public function __construct()
    {
        // Primero intentar desde BD, luego config, luego default
        $this->provider = Setting::get('ai.provider', config('ai.provider', 'simulated'));
        
        // Cargar configuración según el proveedor
        $this->config = match($this->provider) {
            'openai' => [
                'api_key' => Setting::get('ai.openai_api_key', config('ai.openai.api_key')),
                'model' => Setting::get('ai.openai_model', config('ai.openai.model', 'gpt-4o-mini')),
                'temperature' => (float) Setting::get('ai.openai_temperature', config('ai.openai.temperature', 0.3)),
                'max_tokens' => (int) Setting::get('ai.openai_max_tokens', config('ai.openai.max_tokens', 2000)),
            ],
            'gemini' => [
                'api_key' => Setting::get('ai.gemini_api_key', config('ai.gemini.api_key')),
                'model' => Setting::get('ai.gemini_model', config('ai.gemini.model', 'gemini-flash-latest')),
                'temperature' => (float) Setting::get('ai.gemini_temperature', config('ai.gemini.temperature', 0.3)),
            ],
            'claude' => [
                'api_key' => Setting::get('ai.claude_api_key', config('ai.claude.api_key')),
                'model' => Setting::get('ai.claude_model', config('ai.claude.model', 'claude-3-haiku-20240307')),
                'max_tokens' => (int) Setting::get('ai.claude_max_tokens', config('ai.claude.max_tokens', 2000)),
            ],
            default => [
                'compliance_rate' => Setting::get('ai.simulated_compliance_rate', 75),
            ],
        };
    }

    /**
     * Obtiene el proveedor actual
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Evalúa una interacción usando IA
     */
    public function evaluateInteraction(Interaction $interaction): ?Evaluation
    {
        $campaign = $interaction->campaign;
        $formVersion = $campaign->activeFormVersion;

        if (!$formVersion) {
            Log::warning("No hay ficha de calidad activa para la campaña {$campaign->id}");
            return null;
        }

        // Preparar el prompt para la IA
        $prompt = $this->buildEvaluationPrompt($interaction, $formVersion);

        try {
            // Llamar al proveedor de IA configurado
            $rawResponseContent = null;
            $parsedResponse = null;

            match($this->provider) {
                'openai' => $parsedResponse = $this->callOpenAI($prompt, $rawResponseContent),
                'gemini' => $parsedResponse = $this->callGemini($prompt, $rawResponseContent),
                'claude' => $parsedResponse = $this->callClaude($prompt, $rawResponseContent),
                default => $parsedResponse = $this->simulateAIResponse($prompt, $rawResponseContent),
            };
            
            if (!$parsedResponse) {
                Log::error("Error al llamar a {$this->provider} para interacción {$interaction->id}");
                return null;
            }

            // Procesar la respuesta
            $evaluation = $this->processAIResponse($interaction, $formVersion, $parsedResponse, $prompt, $rawResponseContent ?? 'No raw content available');

            return $evaluation;
        } catch (\Exception $e) {
            Log::error("Error en evaluación IA ({$this->provider}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Construye el prompt para la evaluación
     */
    protected function buildEvaluationPrompt(Interaction $interaction, QualityFormVersion $formVersion): string
    {
        $criteria = [];
        foreach ($formVersion->formAttributes as $attribute) {
            foreach ($attribute->subAttributes as $subAttribute) {
                $criteria[] = [
                    'id' => $subAttribute->id,
                    'attribute' => $attribute->name,
                    'name' => $subAttribute->name,
                    'concept' => $subAttribute->concept ?? 'Sin descripción',
                    'weight' => ($attribute->weight * $subAttribute->weight_percent) / 100,
                    'is_critical' => $subAttribute->is_critical,
                ];
            }
        }

        $criteriaJson = json_encode($criteria, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Eres un experto analista de calidad de atención al cliente. Tu tarea es evaluar la siguiente transcripción de una llamada telefónica.

## TRANSCRIPCIÓN
{$interaction->transcript_text}

## CRITERIOS DE EVALUACIÓN
{$criteriaJson}

## INSTRUCCIONES
Para cada criterio, debes:
1. Determinar si el agente CUMPLE o NO CUMPLE
2. Proporcionar una cita textual de la transcripción como evidencia
3. Dar un nivel de confianza entre 0 y 1
4. Agregar notas breves si es necesario

Adicionalmente, genera un "general_feedback" CONSTRUCTIVO y ESTRUCTURADO que incluya:
- Resumen del desempeño
- Fortalezas detectadas (Puntos fuertes del agente)
- Áreas de mejora (Oportunidades de coaching)
- Recomendaciones accionables

## FORMATO DE RESPUESTA
CRÍTICO: Responde ÚNICAMENTE con JSON válido. NO incluyas bloques de código markdown (```), NO agregues explicaciones adicionales, NO agregues comentarios. SOLO el JSON puro.

Estructura requerida:
{
    "items": [
        {
            "id": [ID del subatributo],
            "status": "compliant" | "non_compliant" | "not_found",
            "evidence_quote": "cita textual de la transcripción",
            "confidence": 0.0-1.0,
            "notes": "notas opcionales"
        }
    ],
    "general_feedback": "Texto en formato MARKDOWN limpio. Estructura requerida:\n\n### 🤖 Resumen del Desempeño\n[Resumen aquí]\n\n### ✅ Fortalezas\n- [Fortaleza 1]: [Detalle]\n- [Fortaleza 2]: [Detalle]\n\n### ⚠️ Oportunidades de Mejora\n- [Oportunidad 1]: [Detalle]\n- [Oportunidad 2]: [Detalle]"
}

Responde AHORA con el JSON (sin markdown, sin bloques de código):
PROMPT;
    }

    /**
     * Llama a la API de OpenAI
     */
    protected function callOpenAI(string $prompt, ?string &$rawResponseContent = null): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;
        
        if (empty($apiKey)) {
            Log::warning("API Key de OpenAI no configurada, usando evaluación simulada");
            return $this->simulateAIResponse($prompt);
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['model'] ?? 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un experto analista de calidad. Responde siempre en formato JSON válido.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->config['temperature'] ?? 0.3,
                'max_tokens' => $this->config['max_tokens'] ?? 2000,
            ]);

        if ($response->failed()) {
            Log::error("Error en API OpenAI: " . $response->body());
            return null;
        }

        $rawResponseContent = $response->json('choices.0.message.content');
        return $this->parseJsonResponse($rawResponseContent);
    }

    /**
     * Llama a la API de Google Gemini
     */
    protected function callGemini(string $prompt, ?string &$rawResponseContent = null): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;
        
        if (empty($apiKey)) {
            Log::warning("API Key de Gemini no configurada, usando evaluación simulada");
            return $this->simulateAIResponse($prompt);
        }

        $model = $this->config['model'] ?? 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(60)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "Eres un experto analista de calidad. Responde siempre en formato JSON válido.\n\n{$prompt}"]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->config['temperature'] ?? 0.3,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ]
        ]);

        if ($response->failed()) {
            Log::error("Error en API Gemini: " . $response->body());
            return null;
        }

        $rawResponseContent = $response->json('candidates.0.content.parts.0.text');
        return $this->parseJsonResponse($rawResponseContent);
    }

    /**
     * Llama a la API de Anthropic Claude
     */
    protected function callClaude(string $prompt, ?string &$rawResponseContent = null): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;
        
        if (empty($apiKey)) {
            Log::warning("API Key de Claude no configurada, usando evaluación simulada");
            return $this->simulateAIResponse($prompt);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
        ->timeout(60)
        ->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->config['model'] ?? 'claude-3-haiku-20240307',
            'max_tokens' => $this->config['max_tokens'] ?? 2000,
            'system' => 'Eres un experto analista de calidad. Responde siempre en formato JSON válido.',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error("Error en API Claude: " . $response->body());
            return null;
        }

        $rawResponseContent = $response->json('content.0.text');
        return $this->parseJsonResponse($rawResponseContent);
    }

    /**
     * Parsea la respuesta JSON de la IA
     */
    protected function parseJsonResponse(?string $content): ?array
    {
        if (empty($content)) return null;

        $jsonString = $content;
        
        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonString = $matches[1];
        }

        // Attempt normal decode
        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) return $result;

        // Common fix: Remove trailing commas
        $jsonString = preg_replace('/,\s*}/', '}', $jsonString);
        $jsonString = preg_replace('/,\s*]/', ']', $jsonString);

        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) return $result;
        
        // Last resort: find first { and last }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        
        if ($start !== false && $end !== false && $end > $start) {
            $jsonString = substr($content, $start, $end - $start + 1);
            $result = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE) return $result;
        }

        Log::error("Error parseando respuesta JSON: " . json_last_error_msg());
        // Log truncated content to avoid flooding logs if it's huge
        Log::error("Content snippet: " . substr($content, 0, 500) . " ... " . substr($content, -500));
        return null;
    }

    /**
     * Simula una respuesta de IA para desarrollo/testing
     */
    protected function simulateAIResponse(string $prompt, ?string &$rawResponseContent = null): array
    {
        $rawResponseContent = '{"simulated": true, "items": [...]}'; // Simplified for simulation
        // Extraer los IDs de los criterios del prompt
        preg_match_all('/"id":\s*(\d+)/', $prompt, $matches);
        $ids = $matches[1] ?? [];

        $complianceRate = config('ai.simulated.compliance_rate', 75);

        // Extract sentences from transcript for "evidence"
        preg_match('/Transcr(?:ipción|ipt)\s*[:\n]+(.*)/is', $prompt, $transcriptMatches);
        $transcriptText = $transcriptMatches[1] ?? '';
        // Split by simple punctuation
        $sentences = preg_split('/(?<=[.?!])\s+/', strip_tags($transcriptText));
        $sentences = array_values(array_filter($sentences, fn($s) => strlen($s) > 15));
        
        $items = [];
        foreach ($ids as $index => $id) {
            $isCompliant = rand(0, 100) < $complianceRate;
            
            // Pick a random sentence as evidence if available
            $quote = !empty($sentences) 
                ? $sentences[array_rand($sentences)]
                : ($isCompliant 
                    ? 'El agente demostró cumplimiento de este criterio durante la llamada.' 
                    : 'No se encontró evidencia clara de cumplimiento.');

            $items[] = [
                'id' => (int) $id,
                'status' => $isCompliant ? 'compliant' : 'non_compliant',
                'evidence_quote' => trim($quote),
                'confidence' => rand(85, 99) / 100,
                'notes' => null,
            ];
        }

        return [
            'items' => $items,
            'general_feedback' => '⚠️ Evaluación SIMULADA para desarrollo. Configure AI_PROVIDER y las credenciales correspondientes en .env para evaluaciones reales con IA.',
        ];
    }

    /**
     * Procesa la respuesta de la IA y crea la evaluación
     */
    protected function processAIResponse(Interaction $interaction, QualityFormVersion $formVersion, array $response, string $prompt, string $rawResponse): Evaluation
    {
        // Crear la evaluación
        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'form_version_id' => $formVersion->id,
            'ai_processed_at' => now(),
            'ai_model' => $this->getModelName(),
            'ai_summary' => $response['general_feedback'] ?? null,
            'ai_prompt' => $prompt,
            'ai_raw_response' => $rawResponse,
            'status' => 'visible_to_agent',
        ]);

        // Crear items de evaluación
        $totalScore = 0;
        $totalWeight = 0;

        foreach ($response['items'] ?? [] as $itemData) {
            $subAttributeId = $itemData['id'];

            $subAttribute = $formVersion->formAttributes()
                ->join('quality_subattributes', 'quality_attributes.id', '=', 'quality_subattributes.attribute_id')
                ->where('quality_subattributes.id', $subAttributeId)
                ->select('quality_subattributes.*')
                ->first();

            if (!$subAttribute) {
                Log::warning("SubAttribute ID {$subAttributeId} not found in form version {$formVersion->id}");
                continue;
            }

            $attribute = $formVersion->formAttributes->firstWhere('id', $subAttribute->attribute_id);
            $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;

            $score = match($itemData['status']) {
                'compliant' => 1,
                'non_compliant' => 0,
                'not_found' => 0.5,
                default => 0,
            };

            // Para criterios críticos que no cumplen, penalizar más
            if ($subAttribute->is_critical && $itemData['status'] === 'non_compliant') {
                $score = 0;
            }

            EvaluationItem::create([
                'evaluation_id' => $evaluation->id,
                'subattribute_id' => $itemData['id'],
                'status' => $itemData['status'],
                'score' => $score,
                'max_score' => 1,
                'weighted_score' => $score * $effectiveWeight,
                'confidence' => $itemData['confidence'] ?? null,
                'evidence_quote' => $itemData['evidence_quote'] ?? null,
                'evidence_reference' => null,
                'ai_notes' => $itemData['notes'] ?? null,
            ]);

            $totalScore += $score * $effectiveWeight;
            $totalWeight += $effectiveWeight;
        }

        // Calcular puntaje final
        $percentageScore = $totalWeight > 0 ? ($totalScore / $totalWeight) * 100 : 0;

        $evaluation->update([
            'total_score' => $totalScore,
            'percentage_score' => round($percentageScore, 2),
        ]);

        // Notificar al agente
        if ($evaluation->agent) {
             $evaluation->agent->notify(new \App\Notifications\EvaluationCompleted($evaluation));
        }

        return $evaluation;
    }

    /**
     * Obtiene el nombre del modelo usado
     */
    protected function getModelName(): string
    {
        return match($this->provider) {
            'openai' => $this->config['model'] ?? 'gpt-4o-mini',
            'gemini' => $this->config['model'] ?? 'gemini-1.5-flash',
            'claude' => $this->config['model'] ?? 'claude-3-haiku',
            default => 'simulated',
        };
    }

    /**
     * Evaluar múltiples interacciones en cola
     */
    public function evaluatePendingInteractions(int $limit = 10): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'provider' => $this->provider,
        ];

        $interactions = Interaction::whereDoesntHave('evaluation')
            ->whereHas('campaign', function ($query) {
                $query->whereNotNull('active_form_version_id');
            })
            ->limit($limit)
            ->get();

        foreach ($interactions as $interaction) {
            $evaluation = $this->evaluateInteraction($interaction);
            
            if ($evaluation) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Generar reporte de insights agrupados
     */
    public function generateInsightReport(\Illuminate\Database\Eloquent\Collection $evaluations, string $type = 'combined'): array
    {
        // Obtener contexto de campaña y ficha de calidad
        $firstEval = $evaluations->first();
        $campaign = $firstEval->campaign;
        $qualityForm = $firstEval->formVersion;

        // Recopilar criterios de la ficha de calidad
        $qualityCriteria = [];
        if ($qualityForm) {
            foreach ($qualityForm->formAttributes as $attr) {
                foreach ($attr->subAttributes as $sub) {
                    $qualityCriteria[] = [
                        'category' => $attr->name,
                        'criterion' => $sub->name,
                        'is_critical' => $sub->is_critical,
                    ];
                }
            }
        }
        $criteriaJson = json_encode($qualityCriteria, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Recopilar datos de fallas
        $failedItems = [];
        foreach ($evaluations as $eval) {
            foreach ($eval->items as $item) {
                if ($item->status === 'non_compliant') {
                    $failedItems[] = [
                        'criteria' => $item->subAttribute->name ?? 'Unknown',
                        'evidence' => $item->evidence_quote,
                        'notes' => $item->ai_notes,
                        'is_critical' => $item->subAttribute->is_critical ?? false
                    ];
                }
            }
        }

        // Si no hay suficiente data
        if (count($failedItems) < 3) {
            return $this->simulateInsightResponse($type);
        }

        // Limitar para no exceder tokens
        $sampleFailures = array_slice($failedItems, 0, 50);
        $failuresJson = json_encode($sampleFailures, JSON_UNESCAPED_UNICODE);

        // 2. Construir Prompt Diferenciado
        $roleInstruction = match($type) {
            'operational' => "Eres un Gerente de Operaciones de Call Center. Tu objetivo es mejorar el desempeño de los agentes.",
            'strategic' => "Eres un Consultor de Negocios para el Cliente Corporativo (la marca). Tu objetivo es detectar fricciones en el producto/proceso.",
            default => "Eres un experto analista de calidad de Call Center."
        };

        $focusInstruction = match($type) {
            'operational' => "Céntrate en: Errores de script, falta de empatía, muletillas, no seguir procedimientos, tiempos muertos.",
            'strategic' => "Céntrate en: Quejas recurrentes sobre el producto, procesos burocráticos que molestan al cliente, confusión con facturación/promociones.",
            default => "Analiza tanto el desempeño del agente como los problemas del proceso."
        };

        $prompt = <<<PROMPT
{$roleInstruction}

**CONTEXTO DE LA CAMPAÑA:**
- Nombre: {$campaign->name}
- Descripción: {$campaign->description}

**CRITERIOS DE LA FICHA DE CALIDAD:**
{$criteriaJson}

**DATOS DE FALLAS DETECTADAS:**
{$failuresJson}

{$focusInstruction}

**INSTRUCCIONES CRÍTICAS:**
1. TODO el análisis debe estar en ESPAÑOL
2. Basa tu análisis EXCLUSIVAMENTE en los criterios de la ficha de calidad mostrados arriba
3. Relaciona cada hallazgo con criterios específicos de la ficha
4. Menciona qué criterios de la campaña "{$campaign->name}" se están incumpliendo
5. Sé ESPECÍFICO y ACCIONABLE

Genera un reporte EXHAUSTIVO en formato JSON (TODO EN ESPAÑOL):
{
    "executive_summary": "Resumen ejecutivo en Markdown. INCLUYE: contexto de la campaña, criterios más problemáticos, conclusión.",
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
    ]
}

GENERA MÍNIMO: 3 improvement_opportunities, 2 deficiencies, 3 recommendations.
RECUERDA: TODO en español, basado en la ficha de calidad de "{$campaign->name}".
PROMPT;

        $response = match($this->provider) {
            'gemini' => $this->callGemini($prompt),
            'openai' => $this->callOpenAI($prompt),
            'claude' => $this->callClaude($prompt),
            default => $this->simulateInsightResponse($type),
        };

        return $response ?? $this->simulateInsightResponse($type);
    }

    protected function simulateInsightResponse(string $type): array
    {
        return [
            'executive_summary' => "### 📊 Reporte Simulado ({$type})\n\nEste es un análisis generado automáticamente para pruebas. En producción, esto usaría IA real para analizar tendencias y patrones en las evaluaciones.\n\n**Nota:** Configure un proveedor de IA (Gemini/OpenAI) para obtener insights reales.",
            'improvement_opportunities' => [
                [
                    'category' => 'Script Adherence',
                    'description' => 'Múltiples agentes omiten el saludo corporativo completo',
                    'affected_count' => 8,
                    'priority' => 'High',
                    'coaching_actions' => 'Reforzar roleplay de apertura en sesiones 1:1'
                ],
                [
                    'category' => 'Empathy',
                    'description' => 'Falta de validación emocional en quejas de clientes',
                    'affected_count' => 5,
                    'priority' => 'Medium',
                    'coaching_actions' => 'Capacitación en técnicas de escucha activa'
                ],
                [
                    'category' => 'Product Knowledge',
                    'description' => 'Confusión sobre términos de promociones vigentes',
                    'affected_count' => 3,
                    'priority' => 'Medium',
                    'coaching_actions' => 'Quiz semanal de actualizaciones de producto'
                ]
            ],
            'deficiencies' => [
                [
                    'title' => 'Script Desactualizado',
                    'description' => 'El script de apertura no refleja las promociones actuales',
                    'frequency' => 'Recurrente',
                    'root_cause' => 'Falta de comunicación entre Marketing y Operaciones',
                    'recommendation' => 'Establecer proceso de actualización quincenal de scripts'
                ],
                [
                    'title' => 'Tiempo de Espera Elevado',
                    'description' => 'Clientes reportan tiempos de espera prolongados al verificar información',
                    'frequency' => 'Frecuente',
                    'root_cause' => 'Sistema CRM lento o agentes no capacitados en atajos',
                    'recommendation' => 'Optimización técnica + capacitación en uso eficiente de herramientas'
                ]
            ],
            'product_issues' => [
                [
                    'issue' => 'Confusión en Facturación de Extras',
                    'customer_impact' => 'Clientes se sorprenden con cargos no explicados previamente',
                    'evidence' => 'Múltiples llamadas por consultas de facturación',
                    'suggested_fix' => 'Mejorar transparencia en documentación de cargos adicionales'
                ]
            ],
            'trends' => [
                'overall_direction' => 'stable',
                'key_observations' => 'El desempeño se mantiene estable sin cambios significativos',
                'critical_changes' => 'Ninguno detectado en el periodo analizado'
            ],
            'recommendations' => [
                [
                    'priority' => 1,
                    'action' => 'Actualizar script de apertura con promociones vigentes',
                    'expected_impact' => 'Reducción del 30% en consultas sobre promociones',
                    'responsible' => 'Operaciones + Marketing'
                ],
                [
                    'priority' => 2,
                    'action' => 'Implementar sesiones de coaching 1:1 para agentes de bajo desempeño',
                    'expected_impact' => 'Mejora de 10-15 puntos en puntaje promedio',
                    'responsible' => 'Supervisores'
                ],
                [
                    'priority' => 3,
                    'action' => 'Revisión de UX en facturación digital',
                    'expected_impact' => 'Reducción de llamadas de consulta',
                    'responsible' => 'Producto/Negocio'
                ]
            ]
        ];
    }
}
