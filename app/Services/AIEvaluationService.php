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
    protected const EVALUATION_SYSTEM_INSTRUCTION = 'Eres un analista de calidad de atención al cliente. Evalúa solo con la evidencia disponible, los criterios configurados y el contexto operativo. No inventes hechos, intenciones ni datos no presentes.';

    protected const JSON_SYSTEM_INSTRUCTION = 'Responde únicamente con JSON válido. No incluyas markdown, bloques de código ni explicaciones fuera del objeto JSON.';

    protected string $provider;

    protected array $config;

    protected array $lastAiProviderMetadata = [];

    protected ?GeminiContextCacheService $geminiContextCache = null;

    protected AiResponseParser $responseParser;

    public function __construct(?GeminiContextCacheService $geminiContextCache = null, ?AiResponseParser $responseParser = null)
    {
        $this->provider = AiSettings::provider();
        $this->config = AiSettings::providerConfig($this->provider);
        $this->geminiContextCache = $geminiContextCache;
        $this->responseParser = $responseParser ?? new AiResponseParser();
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
        $chain = AiSettings::failoverChain($this->provider);

        foreach ($chain as $attemptProvider) {
            $config = AiSettings::providerConfig($attemptProvider);
            $apiKey = $config['api_key'] ?? null;

            if (empty($apiKey)) {
                continue;
            }

            try {
                $rawContent = match ($attemptProvider) {
                    'openai' => $this->callOpenAIText($prompt),
                    'gemini' => $this->callGeminiText($prompt),
                    'claude' => $this->callClaudeText($prompt),
                    default => null,
                };

                if ($rawContent !== null) {
                    if ($attemptProvider !== $this->provider) {
                        Log::info("AI failover (analyze): {$this->provider} → {$attemptProvider}");
                    }

                    return $rawContent;
                }
            } catch (\Exception $e) {
                Log::warning("AI failover (analyze): {$attemptProvider} falló: ".$e->getMessage());
                continue;
            }
        }

        Log::error('AIEvaluationService::analyze - Todos los proveedores fallaron');

        return null;
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
                    ['role' => 'system', 'content' => 'Eres un experto analista de calidad de call centers. Responde exactamente con lo que se te pide, sin texto adicional fuera del formato solicitado.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000,
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
                'maxOutputTokens' => 3000,
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
                'max_tokens' => 3000,
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

        $promptParts = $this->buildEvaluationPromptParts($interaction, $formVersion);
        $prompt = $promptParts['full'];

        // Failover chain: try each provider in order until one succeeds
        $chain = AiSettings::failoverChain($this->provider);
        $lastException = null;

        foreach ($chain as $attemptProvider) {
            try {
                $rawResponseContent = null;
                $parsedResponse = null;
                $this->lastAiProviderMetadata = [];

                // Switch provider context for this attempt
                $previousProvider = $this->provider;
                $this->provider = $attemptProvider;
                $this->config = AiSettings::providerConfig($attemptProvider);

                $parsedResponse = match ($attemptProvider) {
                    'openai' => $this->callOpenAI($prompt, $rawResponseContent),
                    'gemini' => $this->callGemini($prompt, $rawResponseContent, $formVersion, $promptParts['static'], $promptParts['dynamic']),
                    'claude' => $this->callClaude($prompt, $rawResponseContent),
                    default => $this->simulateAIResponse($prompt, $rawResponseContent),
                };

                if (! $parsedResponse) {
                    Log::warning("Proveedor AI {$attemptProvider} retornó null para interacción {$interaction->id}, intentando siguiente...");
                    $this->provider = $previousProvider;
                    continue;
                }

                // Success — log if we fell back to a different provider
                if ($attemptProvider !== $previousProvider) {
                    Log::info("AI failover: {$previousProvider} → {$attemptProvider} para interacción {$interaction->id}");
                }

                // Procesar la respuesta
                $evaluation = $this->processAIResponse($interaction, $formVersion, $parsedResponse, $prompt, $rawResponseContent ?? 'No raw content available', $existingEvaluation, $this->lastAiProviderMetadata);

                return $evaluation;
            } catch (TransientAiProviderException $e) {
                $lastException = $e;
                Log::warning("AI failover: {$attemptProvider} falló (transitorio) para interacción {$interaction->id}: ".$e->getMessage());
                $this->provider = $previousProvider ?? $this->provider;
                continue; // Try next provider
            } catch (PermanentAiProviderException $e) {
                $lastException = $e;
                Log::warning("AI failover: {$attemptProvider} falló (permanente) para interacción {$interaction->id}: ".$e->getMessage());
                $this->provider = $previousProvider ?? $this->provider;
                continue; // Try next provider (issue may be provider-specific)
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error("AI failover: {$attemptProvider} error inesperado para interacción {$interaction->id}: ".$e->getMessage());
                $this->provider = $previousProvider ?? $this->provider;
                continue;
            }
        }

        // All providers failed
        if ($lastException) {
            Log::error("AI failover: Todos los proveedores fallaron para interacción {$interaction->id}");
            throw $lastException;
        }

        return null;
    }

    /**
     * Construye el prompt para la evaluación
     */
    protected function buildEvaluationPrompt(Interaction $interaction, QualityFormVersion $formVersion): string
    {
        return $this->buildEvaluationPromptParts($interaction, $formVersion)['full'];
    }

    /**
     * @return array{static: string, dynamic: string, full: string}
     */
    protected function buildEvaluationPromptParts(Interaction $interaction, QualityFormVersion $formVersion): array
    {
        $formVersion->loadMissing('form');

        $criteria = [];
        foreach ($formVersion->formAttributes as $attribute) {
            foreach ($attribute->subAttributes as $subAttribute) {
                $criterion = [
                    'id' => $subAttribute->id,
                    'a' => $attribute->name,
                    'n' => $subAttribute->name,
                    'w' => round(($attribute->weight * $subAttribute->weight_percent) / 100, 4),
                    'mp' => (bool) $subAttribute->is_critical,
                ];

                $description = trim((string) ($subAttribute->concept ?? ''));
                if ($description !== '' && mb_strtolower($description) !== 'sin descripción') {
                    $criterion['d'] = $description;
                }

                $criteria[] = $criterion;
            }
        }

        $criteriaJson = $this->compactJson($criteria);

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
Usa estas señales como apoyo para evaluar criterios relacionados con empatía, manejo emocional, interrupciones, claridad, ritmo y experiencia del cliente. Leyenda compacta: dur=duración, sent=sentimiento (o=global,sum=resumen), ac=acústica (ar=asesor_wpm,cr=cliente_wpm,pace=ritmo,int=interrupciones), sig=señales de calidad (emp=empatía,obj=objeciones,cx=riesgo_cliente,sum=resumen), seg=segmentos emocionales. No reemplazan la evidencia textual.

{$audioContext}

AUDIO
            : '';

        $systemInstruction = self::EVALUATION_SYSTEM_INSTRUCTION;

        $staticPrompt = <<<PROMPT
## ROL E INSTRUCCIONES DEL EVALUADOR
{$systemInstruction}
Versión de prompt: {$this->promptVersion()}.

## CRITERIOS DE EVALUACIÓN
Leyenda compacta: id=ID del subatributo, a=atributo/categoría, n=criterio, d=descripción, w=peso, mp=mala práctica crítica.
{$criteriaJson}

{$operationalContextBlock}

## CALIBRACIÓN Y CRITERIOS
- No infieras hechos, intenciones, precios, validaciones, promesas o gestiones que no aparezcan en la transcripción, señales de audio o contexto operativo.
- Si un criterio exige frase, cláusula, precio, restricción o validación exacta, marca "non_compliant" cuando no haya evidencia explícita suficiente.
- Si un criterio evalúa intención comunicativa, empatía, escucha activa o claridad, permite equivalencias semánticas razonables solo cuando el criterio lo permita.
- Si no existe evidencia para evaluar un criterio porque no aplica al caso, usa "not_found".
- Para "non_compliant", entrega una cita textual o explica en notes que la omisión explícita es la evidencia.
- Ante conflicto entre contexto operativo y lo dicho por el asesor, usa el contexto operativo como fuente de verdad.

## INSTRUCCIONES ESTRICTAS
1. Determinar si el agente CUMPLE o NO CUMPLE cada criterio.
2. Proporcionar una cita textual de la transcripción como evidencia.
3. Dar un nivel de confianza entre 0 y 1.
4. Agregar notas breves si es necesario.

REGLA CRÍTICA DE IDIOMA: Absolutamente TODO el texto generado por ti (feedback, notes, evidence_quote) DEBE estar estrictamente en ESPAÑOL. Está prohibido responder en inglés. (Las claves del JSON como 'status' y 'confidence' sí deben mantenerse como se definen en el formato).

Adicionalmente, genera un objeto "feedback" CONSTRUCTIVO y ACCIONABLE con 5 campos. Cada campo debe mencionar criterios específicos de la ficha y citar evidencia de la transcripción cuando sea posible:
- performanceSummary: resumen del desempeño mencionando los 2-3 criterios más relevantes (cumplidos o fallidos) con referencia a la evidencia. Máximo 400 caracteres.
- productKnowledge: análisis de precisión y dominio del producto/servicio, mencionando si hubo errores o aciertos específicos. Máximo 300 caracteres.
- emotionalHandlingAndEmpathy: análisis de tono, empatía y manejo emocional con ejemplos concretos de la llamada. Máximo 300 caracteres.
- strengths: las 2-3 fortalezas más destacadas, mencionando el criterio específico y la evidencia. Máximo 250 caracteres.
- improvementOpportunities: los 2-3 errores más importantes, cada uno con: nombre del criterio fallido, qué pasó, qué debió hacerse. Separar cada error con punto y coma. Máximo 400 caracteres.

REGLAS CRÍTICAS DEL JSON:
- No uses Markdown, bullets, emojis ni encabezados dentro de feedback o notes.
- No uses comillas dobles dentro de los valores de texto. Si necesitas citar algo, usa comillas simples o reformula la frase.
- Mantén cada campo de feedback, notes y evidence_quote en una sola línea.
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
    "feedback": {
        "performanceSummary": "El asesor cumplió el saludo y la despedida, pero falló en empatía cuando el cliente reclamó por facturación y no ofreció una solución concreta.",
        "productKnowledge": "Explicó correctamente el plan vigente, pero confundió las condiciones del descuento promocional mencionando un precio que no corresponde.",
        "emotionalHandlingAndEmpathy": "Mantuvo tono profesional, pero no validó la molestia del cliente cuando dijo 'estoy molesto por los cargos'. Debió decir 'entiendo su molestia'.",
        "strengths": "Saludo corporativo correcto mencionando nombre y empresa; buena escucha activa al repetir los datos del cliente para confirmar.",
        "improvementOpportunities": "Empatía: no validó la molestia del cliente al reclamar, debió reconocer la emoción antes de explicar; Cierre: no confirmó si el cliente quedó satisfecho, debió preguntar '¿hay algo más en que le pueda ayudar?'; Objeción: no manejo la objeción de precio, debió resaltar el valor del servicio."
    }
}
PROMPT;

        $dynamicPrompt = <<<PROMPT
{$audioContextBlock}

## TRANSCRIPCIÓN
{$interaction->transcript_text}
PROMPT;

        return [
            'static' => trim($staticPrompt),
            'dynamic' => trim($dynamicPrompt),
            'full' => trim($staticPrompt)."\n\n".trim($dynamicPrompt),
        ];
    }

    protected function audioContextForPrompt(Interaction $interaction): string
    {
        if (! $interaction->isAudio()) {
            return '';
        }

        $metadata = $interaction->metadata ?? [];
        $payload = array_filter([
            'dur' => $interaction->audio_duration,
            'sent' => $this->compactKnownKeys($metadata['sentiment'] ?? null, [
                'overall' => 'o',
                'summary' => 'sum',
                'score' => 'score',
                'confidence' => 'conf',
            ]),
            'ac' => $this->compactKnownKeys($metadata['acoustic_analysis'] ?? null, [
                'agent_speech_rate_wpm' => 'ar',
                'client_speech_rate_wpm' => 'cr',
                'overall_pace' => 'pace',
                'interruptions' => 'int',
                'silence_ratio' => 'sil',
                'overlap_count' => 'ov',
                'average_volume' => 'vol',
                'noise_level' => 'noise',
                'agent_talk_ratio' => 'atr',
                'client_talk_ratio' => 'ctr',
            ]),
            'sig' => $this->compactKnownKeys($metadata['quality_signals'] ?? null, [
                'empathy' => 'emp',
                'objection_handling' => 'obj',
                'customer_experience_risk' => 'cx',
                'summary' => 'sum',
                'compliance_risk' => 'comp',
                'sentiment_risk' => 'sent',
                'clarity' => 'clar',
            ]),
            'seg' => collect($metadata['sentiment_segments'] ?? $metadata['emotion_segments'] ?? [])
                ->filter(fn ($segment): bool => is_array($segment))
                ->map(fn (array $segment): array => array_filter([
                    'i' => $segment['index'] ?? null,
                    't' => $segment['start'] ?? null,
                    'sp' => $segment['speaker'] ?? null,
                    's' => $segment['sentiment'] ?? null,
                    'e' => $segment['emotion'] ?? null,
                    'score' => $segment['score'] ?? null,
                    'tone' => $segment['tone'] ?? null,
                    'pace' => $segment['pace'] ?? null,
                    'vol' => $segment['volume'] ?? null,
                    'clar' => $segment['clarity'] ?? null,
                    'ev' => $segment['evidence'] ?? null,
                ], fn ($value): bool => $value !== null && $value !== ''))
                ->take(12)
                ->values()
                ->all(),
        ], fn ($value): bool => ! empty($value));

        if ($payload === []) {
            return '';
        }

        return $this->compactJson($payload);
    }

    protected function compactKnownKeys(mixed $value, array $keyMap): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $compact = [];
        foreach ($value as $key => $item) {
            $compact[$keyMap[$key] ?? $key] = is_array($item)
                ? $this->compactKnownKeys($item, $keyMap)
                : $item;
        }

        return array_filter($compact, fn ($item): bool => $item !== null && $item !== '');
    }

    protected function compactJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    protected function promptVersion(): string
    {
        return AiSettings::PROMPT_VERSION;
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
                    ['role' => 'system', 'content' => self::JSON_SYSTEM_INSTRUCTION],
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

        return $this->responseParser->parse($rawResponseContent);
    }

    /**
     * Llama a la API de Google Gemini
     */
    protected function callGemini(
        string $prompt,
        ?string &$rawResponseContent = null,
        ?QualityFormVersion $formVersion = null,
        ?string $staticPrompt = null,
        ?string $dynamicPrompt = null
    ): ?array
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            Log::error('API Key de Gemini no configurada en IA y Modelos.');

            throw new PermanentAiProviderException('API Key de Gemini no configurada en IA y Modelos.', 'gemini');
        }

        $model = $this->config['model'] ?? AiSettings::DEFAULTS['gemini_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $cacheResult = [
            'cache_id' => null,
            'token_count' => null,
            'status' => 'disabled',
            'hash' => null,
        ];
        $cacheId = null;
        $requestText = $prompt;

        if ($formVersion && $staticPrompt && $dynamicPrompt) {
            $cacheResult = $this->geminiContextCache()->cacheFor($formVersion, $staticPrompt, $model, $apiKey, self::JSON_SYSTEM_INSTRUCTION);
            $cacheId = $cacheResult['cache_id'] ?? null;
            $requestText = $cacheId ? $dynamicPrompt : $prompt;
        }

        $response = Http::timeout(120)->post($url, $this->geminiPayload($requestText, $cacheId));

        if ($response->failed()) {
            $message = $response->json('error.message', $response->body());

            if ($cacheId && $formVersion && $this->geminiContextCache()->isInvalidCacheError($response->status(), (string) $message)) {
                $this->geminiContextCache()->clear($formVersion);
                $cacheResult['status'] = 'invalid_retry_full';
                $cacheId = null;
                $response = Http::timeout(120)->post($url, $this->geminiPayload($prompt, null));
            }
        }

        if ($response->failed()) {
            Log::error('Error en API Gemini: '.$response->body());

            $message = $response->json('error.message', $response->body());
            throw AiProviderErrors::exceptionFor('gemini', $response->status(), "Gemini API error: {$message}");
        }

        $rawResponseData = $response->json();
        $rawResponseContent = $rawResponseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $this->lastAiProviderMetadata = $this->geminiUsageMetadata($rawResponseData, $cacheResult, (bool) $cacheId);

        return $this->responseParser->parse($rawResponseContent);
    }

    protected function geminiPayload(string $text, ?string $cacheId = null): array
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => self::JSON_SYSTEM_INSTRUCTION],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text],
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
        ];

        if ($cacheId) {
            $payload['cachedContent'] = $cacheId;
        }

        return $payload;
    }

    protected function geminiUsageMetadata(array $response, array $cacheResult, bool $cacheUsed): array
    {
        $usage = $response['usageMetadata'] ?? $response['usage_metadata'] ?? [];

        return array_filter([
            'gemini_cache_used' => $cacheUsed,
            'gemini_cache_status' => $cacheResult['status'] ?? null,
            'gemini_cache_id' => isset($cacheResult['cache_id']) && $cacheResult['cache_id']
                ? substr(hash('sha256', (string) $cacheResult['cache_id']), 0, 12)
                : null,
            'gemini_cache_token_count' => $cacheResult['token_count'] ?? null,
            'gemini_cached_content_token_count' => $usage['cachedContentTokenCount'] ?? $usage['cached_content_token_count'] ?? null,
            'prompt_token_count' => $usage['promptTokenCount'] ?? $usage['prompt_token_count'] ?? null,
            'candidates_token_count' => $usage['candidatesTokenCount'] ?? $usage['candidates_token_count'] ?? null,
            'total_token_count' => $usage['totalTokenCount'] ?? $usage['total_token_count'] ?? null,
        ], fn ($value): bool => $value !== null);
    }

    protected function geminiContextCache(): GeminiContextCacheService
    {
        return $this->geminiContextCache ??= app(GeminiContextCacheService::class);
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
                'system' => self::JSON_SYSTEM_INSTRUCTION,
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

        return $this->responseParser->parse($rawResponseContent);
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
            'feedback' => [
                'performanceSummary' => 'Evaluación simulada para desarrollo.',
                'productKnowledge' => 'No aplica en modo simulado.',
                'emotionalHandlingAndEmpathy' => 'No aplica en modo simulado.',
                'strengths' => 'Configure un proveedor real para obtener fortalezas detectadas.',
                'improvementOpportunities' => 'Configure un proveedor real para obtener oportunidades de mejora.',
            ],
            'general_feedback' => 'Evaluación simulada para desarrollo. Configure el proveedor y sus credenciales en el módulo IA y Modelos para evaluaciones reales con IA.',
        ];
    }

    /**
     * Procesa la respuesta de la IA y crea la evaluación
     */
    protected function processAIResponse(
        Interaction $interaction,
        QualityFormVersion $formVersion,
        array $response,
        string $prompt,
        string $rawResponse,
        ?Evaluation $existingEvaluation = null,
        array $providerMetadata = []
    ): Evaluation
    {
        $structuredFeedback = $this->normalizeStructuredFeedback($response);

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
            'ai_summary' => $this->summaryFromStructuredFeedback($structuredFeedback, $response['general_feedback'] ?? null),
            'ai_feedback' => $structuredFeedback,
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
                'not_applicable' => 1,
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

        $auditMetadata = array_merge([
            'provider' => $this->provider,
            'model' => $this->getModelName(),
            'items_count' => count($response['items'] ?? []),
            'has_critical_failure' => $hasCriticalFailure,
            'percentage_score' => round($percentageScore, 2),
        ], $providerMetadata);

        $evaluation->recordAuditEvent('ai_evaluated', null, $auditMetadata, $fromStatus, Evaluation::STATUS_PENDING_MONITOR_REVIEW);

        return $evaluation;
    }

    protected function normalizeStructuredFeedback(array $response): array
    {
        $feedback = $response['feedback'] ?? null;

        if (is_array($feedback)) {
            return collect(Evaluation::AI_FEEDBACK_SECTIONS)
                ->mapWithKeys(function (string $title, string $key) use ($feedback) {
                    return [$key => trim((string) ($feedback[$key] ?? ''))];
                })
                ->all();
        }

        $legacy = trim((string) ($response['general_feedback'] ?? ''));

        return collect(Evaluation::AI_FEEDBACK_SECTIONS)
            ->mapWithKeys(fn (string $title, string $key) => [$key => $key === 'performanceSummary' ? $legacy : ''])
            ->all();
    }

    protected function summaryFromStructuredFeedback(array $feedback, ?string $fallback = null): ?string
    {
        $summary = collect(Evaluation::AI_FEEDBACK_SECTIONS)
            ->map(function (string $title, string $key) use ($feedback) {
                $content = trim((string) ($feedback[$key] ?? ''));

                return $content !== '' ? "{$title}: {$content}" : null;
            })
            ->filter()
            ->implode(' ');

        return $summary !== '' ? $summary : $fallback;
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
     * Delegate to InsightReportGenerator for backward compatibility.
     */
    public function generateInsightReport(\Illuminate\Database\Eloquent\Collection $evaluations, string $type = 'combined', array $reportSnapshot = []): array
    {
        $generator = new InsightReportGenerator($this);

        return $generator->generate($evaluations, $type, $reportSnapshot);
    }
}
