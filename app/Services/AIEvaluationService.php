<?php

namespace App\Services;

use App\Exceptions\PermanentAiProviderException;
use App\Exceptions\TransientAiProviderException;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\Interaction;
use App\Models\QualityFormVersion;
use App\Support\AiProviderErrors;
use App\Support\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIEvaluationService
{
    protected string $provider;

    protected array $config;

    public function __construct()
    {
        $this->provider = AiSettings::provider();
        $this->config = AiSettings::providerConfig($this->provider);
    }

    /**
     * Obtiene el proveedor actual
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Analiza un prompt de texto y devuelve un análisis narrativo en texto plano
     */
    public function analyze(string $prompt): ?string
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            Log::warning('AIEvaluationService::analyze - No hay API Key configurada para ' . $this->provider);
            return null;
        }

        try {
            $rawContent = null;

            match ($this->provider) {
                'openai' => $rawContent = $this->callOpenAIText($prompt),
                'gemini' => $rawContent = $this->callGeminiText($prompt),
                'claude' => $rawContent = $this->callClaudeText($prompt),
                default => $rawContent = null,
            };

            return $rawContent;
        } catch (\Exception $e) {
            Log::error('AIEvaluationService::analyze - Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Llama a OpenAI para obtener texto plano (no JSON)
     */
    protected function callOpenAIText(string $prompt): ?string
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (empty($apiKey)) return null;

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['model'] ?? AiSettings::DEFAULTS['openai_model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un experto analista de calidad de call centers. Responde en texto narrativo profesional, sin formato JSON. Usa párrafos claros y concisos.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ]);

        if ($response->failed()) {
            Log::error('callOpenAIText - Error: ' . $response->body());
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Llama a Gemini para obtener texto plano
     */
    protected function callGeminiText(string $prompt): ?string
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (empty($apiKey)) return null;

        $model = $this->config['model'] ?? AiSettings::DEFAULTS['gemini_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(120)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1500,
            ],
        ]);

        if ($response->failed()) {
            Log::error('callGeminiText - Error: ' . $response->body());
            return null;
        }

        return $response->json('candidates.0.content.parts.0.text');
    }

    /**
     * Llama a Claude para obtener texto plano
     */
    protected function callClaudeText(string $prompt): ?string
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (empty($apiKey)) return null;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->config['model'] ?? AiSettings::DEFAULTS['claude_model'],
                'max_tokens' => 1500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('callClaudeText - Error: ' . $response->body());
            return null;
        }

        return $response->json('content.0.text');
    }

    /**
     * Evalúa una interacción usando IA
     */
    public function evaluateInteraction(Interaction $interaction, ?Evaluation $existingEvaluation = null): ?Evaluation
    {
        $campaign = $interaction->campaign;
        $formVersion = $interaction->scorableFormVersion();

        if (! $formVersion) {
            Log::warning("No hay ficha de calidad activa para la campaña {$campaign?->id}. Imposible evaluar.");

            return null;
        }

        // Preparar el prompt para la IA
        $prompt = $this->buildEvaluationPrompt($interaction, $formVersion);

        try {
            // Llamar al proveedor de IA configurado
            $rawResponseContent = null;
            $parsedResponse = null;

            match ($this->provider) {
                'openai' => $parsedResponse = $this->callOpenAI($prompt, $rawResponseContent),
                'gemini' => $parsedResponse = $this->callGemini($prompt, $rawResponseContent),
                'claude' => $parsedResponse = $this->callClaude($prompt, $rawResponseContent),
                default => $parsedResponse = $this->simulateAIResponse($prompt, $rawResponseContent),
            };

            if (! $parsedResponse) {
                Log::error("Error al llamar a {$this->provider} para interacción {$interaction->id}");

                return null;
            }

            // Procesar la respuesta
            $evaluation = $this->processAIResponse($interaction, $formVersion, $parsedResponse, $prompt, $rawResponseContent ?? 'No raw content available', $existingEvaluation);

            return $evaluation;
        } catch (TransientAiProviderException|PermanentAiProviderException $e) {
            Log::warning("Proveedor IA no disponible para interacción {$interaction->id}: ".$e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error("Error en evaluación IA ({$this->provider}): ".$e->getMessage());

            return null;
        }
    }

    /**
     * Obtiene ejemplos "Golden Records" para few-shot learning
     */
    protected function getGoldenExamples(QualityFormVersion $formVersion): string
    {
        // Obtener la evaluación 'gold' más reciente para este formulario
        $golden = Evaluation::where('form_version_id', $formVersion->id)
            ->where('is_gold', true)
            ->with(['interaction', 'items'])
            ->latest()
            ->first();

        if (! $golden || ! $golden->interaction) {
            return '';
        }

        // Construir la estructura JSON esperada
        $items = [];
        foreach ($golden->items as $item) {
            $items[] = [
                'id' => $item->subattribute_id,
                'status' => $item->status,
                'evidence_quote' => $item->evidence_quote ?? '',
                'confidence' => $item->confidence ?? 1.0,
                'notes' => $item->ai_notes ?? '',
            ];
        }

        $expectedJson = json_encode([
            'items' => $items,
            'general_feedback' => $golden->ai_summary ?? 'Resumen del desempeño...',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<EXAMPLE
## EJEMPLO DE REFERENCIA (GOLDEN RECORD)
Usa esta evaluación previa CORRECTA como guía de estilo y criterio:

**Transcripción del Ejemplo:**
{$golden->interaction->transcript_text}

**Evaluación Correcta:**
{$expectedJson}

EXAMPLE;
    }

    /**
     * Construye el prompt para la evaluación
     */
    protected function buildEvaluationPrompt(Interaction $interaction, QualityFormVersion $formVersion): string
    {
        $formVersion->loadMissing('form');

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

        $goldenExamples = $this->getGoldenExamples($formVersion);
        $operationalContext = $formVersion->form?->operationalContextForPrompt() ?: '';
        $operationalContextBlock = $operationalContext
            ? <<<CONTEXT
## CONTEXTO OPERATIVO DE LA CAMPAÑA / SUBCAMPAÑA
Usa esta información como fuente de verdad operativa para evaluar la llamada. Puede incluir precios de productos, características comerciales, speechs obligatorios, cláusulas contractuales, políticas, objeciones esperadas o guías de campaña.

{$operationalContext}

### Reglas para usar el contexto operativo
- Si un criterio exige un speech, cláusula o texto contractual exacto, evalúa si el asesor lo dijo exactamente o con una variación muy semejante que conserve el sentido legal/comercial.
- Si el contexto define precios, productos, condiciones o restricciones, úsalo para validar precisión del asesor.
- No inventes información fuera del contexto operativo. Si el contexto no cubre un punto, evalúa solo con la transcripción y los criterios configurados.
- Si hay conflicto entre lo dicho por el asesor y el contexto operativo, considera el contexto operativo como referencia para detectar la desviación.

CONTEXT
            : '';
        $audioContext = $this->audioContextForPrompt($interaction);
        $audioContextBlock = $audioContext
            ? <<<AUDIO
## SEÑALES DE AUDIO Y EMOCIÓN
Usa estas señales como apoyo para evaluar criterios relacionados con empatía, manejo emocional, interrupciones, claridad, ritmo y experiencia del cliente. No reemplazan la evidencia textual: si una señal acústica contradice la transcripción, explica la duda en notas y baja la confianza.

{$audioContext}

AUDIO
            : '';

        return <<<PROMPT
Eres un experto analista de calidad de atención al cliente. Tu tarea es evaluar la siguiente transcripción de una llamada telefónica.

## TRANSCRIPCIÓN
{$interaction->transcript_text}

{$audioContextBlock}

## CRITERIOS DE EVALUACIÓN
{$criteriaJson}

{$operationalContextBlock}

{$goldenExamples}

## CALIBRACIÓN Y CRITERIOS
- **Sé JUSTO y OBJETIVO:** Evalúa basándote en el *contexto* de la conversación, no solo en palabras clave exactas (a menos que el criterio lo exija explícitamente).
- **Flexibilidad:** Permite variaciones naturales en el habla si se cumple la intención comunicativa del criterio.
- **Evidencia:** Si marcas algo como "non_compliant", DEBES tener una evidencia clara en la transcripción. Ante la duda razonable, favorece al agente o marca "not_found" si no aplica.

## INSTRUCCIONES ESTRICTAS
1. Determinar si el agente CUMPLE o NO CUMPLE cada criterio.
2. Proporcionar una cita textual de la transcripción como evidencia.
3. Dar un nivel de confianza entre 0 y 1.
4. Agregar notas breves si es necesario.

⚠️ REGLA CRÍTICA DE IDIOMA: Absolutamente TODO el texto generado por ti (general_feedback, notes, evidence_quote) DEBE estar estrictamente en ESPAÑOL. Está prohibido responder en inglés. (Las claves del JSON como 'status' y 'confidence' sí deben mantenerse como se definen en el formato).

Adicionalmente, genera un "general_feedback" CONSTRUCTIVO y ESTRUCTURADO que incluya:
- Resumen del desempeño
- Conocimiento del Producto (Precisión y domínio del tema)
- Manejo de Emociones y Empatía (Tono, conexión, manejo de objeciones)
- Fortalezas detectadas
- Oportunidades de mejora

REGLAS CRÍTICAS DEL JSON:
- No uses Markdown, bullets, emojis ni encabezados dentro de general_feedback o notes.
- No uses comillas dobles dentro de los valores de texto. Si necesitas citar algo, usa comillas simples o reformula la frase.
- Mantén general_feedback, notes y evidence_quote en una sola línea cada uno.
- Todos los saltos de línea o caracteres especiales deben estar escapados correctamente para JSON válido.

## FORMATO DE RESPUESTA
Responde ÚNICAMENTE con el siguiente JSON estructurado:

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
    "general_feedback": "Resumen: síntesis breve del desempeño. Conocimiento del producto: análisis de precisión y dominio. Manejo emocional y empatía: análisis de tono, conexión y objeciones. Fortalezas: puntos concretos bien ejecutados. Oportunidades: mejoras accionables para el asesor."
}
PROMPT;
    }

    protected function audioContextForPrompt(Interaction $interaction): string
    {
        if (! $interaction->isAudio()) {
            return '';
        }

        $metadata = $interaction->metadata ?? [];
        $payload = array_filter([
            'duration_seconds' => $interaction->audio_duration,
            'sentiment' => $metadata['sentiment'] ?? null,
            'acoustic_analysis' => $metadata['acoustic_analysis'] ?? null,
            'quality_signals' => $metadata['quality_signals'] ?? null,
            'emotion_segments' => collect($metadata['sentiment_segments'] ?? $metadata['emotion_segments'] ?? [])
                ->filter(fn ($segment): bool => is_array($segment))
                ->take(12)
                ->values()
                ->all(),
        ], fn ($value): bool => ! empty($value));

        if ($payload === []) {
            return '';
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Llama a la API de OpenAI
     */
    protected function callOpenAI(string $prompt, ?string &$rawResponseContent = null): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            Log::error('API Key de OpenAI no configurada en IA y Modelos.');

            throw new PermanentAiProviderException('API Key de OpenAI no configurada en IA y Modelos.', 'openai');
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->config['model'] ?? AiSettings::DEFAULTS['openai_model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un experto analista de calidad. Responde ÚNICAMENTE en formato JSON válido. NO incluyas explicaciones ni texto fuera del objeto JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->config['temperature'] ?? AiSettings::DEFAULTS['openai_temperature'],
                'max_tokens' => $this->config['max_tokens'] ?? AiSettings::DEFAULTS['openai_max_tokens'],
            ]);

        if ($response->failed()) {
            Log::error('Error en API OpenAI: '.$response->body());

            $message = $response->json('error.message', $response->body());
            throw AiProviderErrors::exceptionFor('openai', $response->status(), "OpenAI API error: {$message}");
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
            Log::error('API Key de Gemini no configurada en IA y Modelos.');

            throw new PermanentAiProviderException('API Key de Gemini no configurada en IA y Modelos.', 'gemini');
        }

        $model = $this->config['model'] ?? AiSettings::DEFAULTS['gemini_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Extract system instructions from the prompt for better determinism
        // Let's pass a strong generic system string to systemInstruction,
        // and keep the variable data (transcript, criteria) in the user part.
        $systemInstruction = 'Eres un experto analista de calidad de atención al cliente. Tu misión es evaluar las transcripciones con ESTRICTO APEGO a los criterios proporcionados. DEBES responder ÚNICAMENTE con JSON válido, sin bloques de código markdown, sin explicaciones adicionales. Asegúrate de escapar correctamente comillas internas y saltos de línea.';

        $response = Http::timeout(120)->post($url, [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->config['temperature'] ?? AiSettings::DEFAULTS['gemini_temperature'],
                'topP' => 0.1,
                'topK' => 1,
                'maxOutputTokens' => 65536,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            Log::error('Error en API Gemini: '.$response->body());

            $message = $response->json('error.message', $response->body());
            throw AiProviderErrors::exceptionFor('gemini', $response->status(), "Gemini API error: {$message}");
        }

        $rawResponseData = $response->json();
        $rawResponseContent = $rawResponseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $this->parseJsonResponse($rawResponseContent);
    }

    /**
     * Llama a la API de Anthropic Claude
     */
    protected function callClaude(string $prompt, ?string &$rawResponseContent = null): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            Log::error('API Key de Claude no configurada en IA y Modelos.');

            throw new PermanentAiProviderException('API Key de Claude no configurada en IA y Modelos.', 'claude');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->config['model'] ?? AiSettings::DEFAULTS['claude_model'],
                'temperature' => $this->config['temperature'] ?? AiSettings::DEFAULTS['claude_temperature'],
                'max_tokens' => $this->config['max_tokens'] ?? AiSettings::DEFAULTS['claude_max_tokens'],
                'system' => 'Eres un experto analista de calidad. Responde ÚNICAMENTE en formato JSON válido. NO incluyas explicaciones fuera del JSON. Asegúrate de que todas las comillas internas en el JSON estén correctamente escapadas.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Error en API Claude: '.$response->body());

            $message = $response->json('error.message', $response->body());
            throw AiProviderErrors::exceptionFor('claude', $response->status(), "Claude API error: {$message}");
        }

        $rawResponseContent = $response->json('content.0.text');

        return $this->parseJsonResponse($rawResponseContent);
    }

    /**
     * Parsea la respuesta JSON de la IA
     */
    protected function parseJsonResponse(?string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        $jsonString = $content;

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonString = $matches[1];
        }

        // Attempt normal decode
        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 1: Remove trailing commas
        $jsonString = preg_replace('/,\s*}/', '}', $jsonString);
        $jsonString = preg_replace('/,\s*]/', ']', $jsonString);

        $result = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 2: Remove control characters that break JSON (except newlines)
        $cleanJson = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $jsonString);
        $result = json_decode($cleanJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Common fix 3: Escape unescaped control characters inside JSON strings (preserves formatting)
        $cleanJson = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"/', function ($matches) {
            return str_replace(
                ["\n", "\r", "\t"],
                ['\\n', '\\r', '\\t'],
                preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $matches[0])
            );
        }, $jsonString);
        $result = json_decode($cleanJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        // Last resort: find first { and last }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonString = substr($content, $start, $end - $start + 1);
            $cleanJson = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"/', function ($matches) {
                return str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $matches[0]));
            }, $jsonString);
            $result = json_decode($cleanJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }

        $recovered = $this->recoverEvaluationJsonPayload($content);
        if ($recovered) {
            Log::warning('Respuesta JSON de IA recuperada con parser tolerante.');

            return $recovered;
        }

        Log::error('Error parseando respuesta JSON: '.json_last_error_msg());
        // Log truncated content to avoid flooding logs if it's huge
        Log::error('Content snippet: '.substr($content, 0, 500).' ... '.substr($content, -500));

        return null;
    }

    protected function recoverEvaluationJsonPayload(string $content): ?array
    {
        if (! preg_match_all('/"id"\s*:\s*(\d+)\s*,\s*"status"\s*:\s*"(compliant|non_compliant|not_found)"/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $items = [];
        $ids = $matches[1];
        $statuses = $matches[2];

        foreach ($ids as $index => $idMatch) {
            $itemStart = max(0, $matches[0][$index][1] - 1);
            $nextStart = isset($matches[0][$index + 1])
                ? max(0, $matches[0][$index + 1][1] - 1)
                : strlen($content);
            $slice = substr($content, $itemStart, max(1, $nextStart - $itemStart));

            $confidence = $this->extractJsonLikeNumber($slice, 'confidence');

            $items[] = [
                'id' => (int) $idMatch[0],
                'status' => strtolower($statuses[$index][0]),
                'evidence_quote' => $this->extractJsonLikeString($slice, 'evidence_quote') ?? '',
                'confidence' => $confidence !== null ? max(0, min(1, $confidence)) : null,
                'notes' => $this->extractJsonLikeString($slice, 'notes'),
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'items' => $items,
            'general_feedback' => $this->extractJsonLikeString($content, 'general_feedback')
                ?? 'Evaluación generada por IA. Revise los criterios y evidencias recuperadas en el detalle.',
        ];
    }

    protected function extractJsonLikeString(string $source, string $field): ?string
    {
        $fieldPattern = preg_quote($field, '/');
        if (! preg_match('/"'.$fieldPattern.'"\s*:\s*"(.*?)(?<!\\\\)"\s*(?:,|\})/s', $source, $matches)) {
            return null;
        }

        $value = $matches[1];
        $decoded = json_decode('"'.$value.'"', true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
            return trim(preg_replace('/\s+/', ' ', $decoded));
        }

        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $value)));
    }

    protected function extractJsonLikeNumber(string $source, string $field): ?float
    {
        $fieldPattern = preg_quote($field, '/');
        if (! preg_match('/"'.$fieldPattern.'"\s*:\s*(-?(?:\d*\.\d+|\d+))/', $source, $matches)) {
            return null;
        }

        return (float) $matches[1];
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

        $complianceRate = $this->config['compliance_rate'] ?? AiSettings::DEFAULTS['simulated_compliance_rate'];

        // Extract sentences from transcript for "evidence"
        preg_match('/Transcr(?:ipción|ipt)\s*[:\n]+(.*)/is', $prompt, $transcriptMatches);
        $transcriptText = $transcriptMatches[1] ?? '';
        // Split by simple punctuation
        $sentences = preg_split('/(?<=[.?!])\s+/', strip_tags($transcriptText));
        $sentences = array_values(array_filter($sentences, fn ($s) => strlen($s) > 15));

        $items = [];
        foreach ($ids as $index => $id) {
            $isCompliant = rand(0, 100) < $complianceRate;

            // Pick a random sentence as evidence if available
            $quote = ! empty($sentences)
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
            'general_feedback' => '⚠️ Evaluación SIMULADA para desarrollo. Configure el proveedor y sus credenciales en el módulo IA y Modelos para evaluaciones reales con IA.',
        ];
    }

    /**
     * Procesa la respuesta de la IA y crea la evaluación
     */
    protected function processAIResponse(Interaction $interaction, QualityFormVersion $formVersion, array $response, string $prompt, string $rawResponse, ?Evaluation $existingEvaluation = null): Evaluation
    {
        $evaluationData = [
            'interaction_id' => $interaction->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'form_version_id' => $formVersion->id,
            'type' => 'ai',
            'evaluator_id' => null,
            'ai_processed_at' => now(),
            'ai_model' => $this->getModelName(),
            'ai_provider' => $this->provider,
            'ai_prompt_version' => AiSettings::PROMPT_VERSION,
            'ai_prompt_hash' => hash('sha256', $prompt),
            'ai_settings_snapshot' => AiSettings::versionSnapshot($this->provider),
            'ai_summary' => $response['general_feedback'] ?? null,
            'ai_prompt' => $prompt,
            'ai_raw_response' => $rawResponse,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'published_by' => null,
            'visible_to_agent_at' => null,
            'finalized_at' => null,
        ];

        $fromStatus = $existingEvaluation?->status;

        if ($existingEvaluation) {
            $evaluation = $existingEvaluation;
            $evaluation->items()->delete();
            $evaluation->update($evaluationData);
        } else {
            $evaluation = Evaluation::create($evaluationData);
        }

        // Crear items de evaluación
        $totalScore = 0;
        $totalWeight = 0;
        $hasCriticalFailure = false;

        foreach ($response['items'] ?? [] as $itemData) {
            $subAttributeId = $itemData['id'];

            $subAttribute = $formVersion->formAttributes()
                ->join('quality_subattributes', 'quality_attributes.id', '=', 'quality_subattributes.attribute_id')
                ->where('quality_subattributes.id', $subAttributeId)
                ->select('quality_subattributes.*')
                ->first();

            if (! $subAttribute) {
                Log::warning("SubAttribute ID {$subAttributeId} not found in form version {$formVersion->id}");

                continue;
            }

            $attribute = $formVersion->formAttributes->firstWhere('id', $subAttribute->attribute_id);
            $effectiveWeight = ($attribute->weight * $subAttribute->weight_percent) / 100;

            $score = match ($itemData['status']) {
                'compliant' => 1,
                'non_compliant' => 0,
                'not_found' => 0.5,
                default => 0,
            };

            // Mala Práctica: marcar knockout si falla
            if ($subAttribute->is_critical && $itemData['status'] === 'non_compliant') {
                $hasCriticalFailure = true;
                $score = 0;
            }

            EvaluationItem::create([
                'evaluation_id' => $evaluation->id,
                'subattribute_id' => $itemData['id'],
                'status' => $itemData['status'],
                'score' => $score,
                'max_score' => 1,
                'weighted_score' => $subAttribute->is_critical ? 0 : $score * $effectiveWeight,
                'confidence' => $itemData['confidence'] ?? null,
                'evidence_quote' => $itemData['evidence_quote'] ?? null,
                'evidence_reference' => null,
                'ai_notes' => $itemData['notes'] ?? null,
            ]);

            // Solo sumar peso de items no-críticos
            if (! $subAttribute->is_critical) {
                $totalScore += $score * $effectiveWeight;
                $totalWeight += $effectiveWeight;
            }
        }

        // Calcular puntaje final — knockout si hay mala práctica
        $percentageScore = $totalWeight > 0 ? ($totalScore / $totalWeight) * 100 : 0;
        if ($hasCriticalFailure) {
            $percentageScore = 0;
        }

        $evaluation->update([
            'total_score' => $totalScore,
            'max_possible_score' => $totalWeight,
            'percentage_score' => round($percentageScore, 2),
        ]);

        $evaluation->recordAuditEvent('ai_evaluated', null, [
            'provider' => $this->provider,
            'model' => $this->getModelName(),
            'items_count' => count($response['items'] ?? []),
            'has_critical_failure' => $hasCriticalFailure,
            'percentage_score' => round($percentageScore, 2),
        ], $fromStatus, Evaluation::STATUS_PENDING_MONITOR_REVIEW);

        return $evaluation;
    }

    /**
     * Obtiene el nombre del modelo usado
     */
    protected function getModelName(): string
    {
        return match ($this->provider) {
            'openai' => $this->config['model'] ?? AiSettings::DEFAULTS['openai_model'],
            'gemini' => $this->config['model'] ?? AiSettings::DEFAULTS['gemini_model'],
            'claude' => $this->config['model'] ?? AiSettings::DEFAULTS['claude_model'],
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

        /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\Interaction> $interactions */
        $interactions = Interaction::whereDoesntHave('aiEvaluation')
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
    public function generateInsightReport(\Illuminate\Database\Eloquent\Collection $evaluations, string $type = 'combined', array $reportSnapshot = []): array
    {
        // Obtener contexto de campaña y ficha de calidad
        $firstEval = $evaluations->first();
        $firstEval?->loadMissing('campaign.parent');
        $campaign = $firstEval?->campaign;
        $campaignName = $reportSnapshot['scope']['campaign_name'] ?? $campaign?->displayName() ?? 'Todas las campañas visibles';
        $campaignDescription = $campaign?->description ?: 'Análisis consolidado sobre evaluaciones reales del periodo seleccionado.';

        // Recopilar criterios de la ficha de calidad
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

        // Recopilar datos de fallas
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

        // Si no hay suficiente data
        if (count($failedItems) < 3) {
            return $this->simulateInsightResponse($type, $reportSnapshot);
        }

        // Limitar para no exceder tokens
        $sampleFailures = array_slice($failedItems, 0, 50);
        $failuresJson = json_encode($sampleFailures, JSON_UNESCAPED_UNICODE);

        // 2. Construir Prompt Diferenciado
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
            $response = match ($this->provider) {
                'gemini' => $this->callGemini($prompt),
                'openai' => $this->callOpenAI($prompt),
                'claude' => $this->callClaude($prompt),
                default => $this->simulateInsightResponse($type, $reportSnapshot),
            };
        } catch (\Throwable $exception) {
            Log::warning('Insight report AI generation failed; using evaluation snapshot fallback.', [
                'provider' => $this->provider,
                'message' => $exception->getMessage(),
            ]);

            $response = null;
        }

        return array_replace_recursive(
            $this->simulateInsightResponse($type, $reportSnapshot),
            is_array($response) ? $response : []
        );
    }

    protected function basicInsightResponse(string $type, array $reportSnapshot): array
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

    protected function simulateInsightResponse(string $type, array $reportSnapshot = []): array
    {
        if (! empty($reportSnapshot)) {
            return $this->basicInsightResponse($type, $reportSnapshot);
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
