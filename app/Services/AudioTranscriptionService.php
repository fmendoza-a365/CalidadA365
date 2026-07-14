<?php

namespace App\Services;

use App\Exceptions\TransientAiProviderException;
use App\Support\AiProviderErrors;
use App\Support\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class AudioTranscriptionService
{
    protected string $apiKey;

    protected string $model;

    public function __construct(
        protected AudioSilenceAnalysisService $silenceAnalysisService,
    ) {
        $config = AiSettings::transcriptionConfig();

        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?: AiSettings::DEFAULTS['gemini_model'];
    }

    /**
     * Transcribe an audio file and analyze sentiment/voice signals using Google Gemini API.
     *
     * @param  string  $filePath  Path relative to the storage disk
     * @param  string  $language  Language hint (default: Spanish)
     * @return array<string, mixed> Transcript text and audio analysis
     *
     * @throws \Exception If transcription fails
     */
    public function transcribe(string $filePath, string $language = 'es'): array
    {
        if (empty($this->apiKey)) {
            Log::warning('AudioTranscriptionService: No hay API Key de Gemini configurada en IA y Modelos; usando transcripción simulada.');

            return $this->simulateTranscription($filePath);
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($filePath)) {
            throw new \Exception("Audio file not found: {$filePath}");
        }

        Log::info("AudioTranscriptionService: Transcribing file {$filePath} with Gemini model {$this->model}");

        try {
            $audioData = $disk->get($filePath);
            $audioDurationSeconds = $this->durationSecondsFromStoredAudio($filePath)
                ?? $this->durationSecondsFromAudioBytes($audioData, $filePath);
            $technicalSilence = $this->silenceAnalysisService->analyzeStoredFile($filePath, $audioDurationSeconds);
            $base64Audio = base64_encode($audioData);
            $mimeType = $this->getMimeType($filePath);

            $prompt = <<<'PROMPT'
TAREA 1 - TRANSCRIPCIÓN:
- Transcribe el audio completo palabra por palabra, sin omitir nada.
- Identifica y diferencia a cada hablante. Usa "Agente:" para el representante y "Cliente:" para quien llama.
  * REGLA DE IDENTIFICACIÓN: El "Agente" es quien suele iniciar el contacto (ej. saludando en nombre de la empresa, ofreciendo ayuda, asesorando o pidiendo validaciones). El "Cliente" es quien explica su problema, responde las validaciones o realiza las consultas de soporte. Si detectas un robot interactivo (IVR) al inicio, asócialo al lado de la empresa o simplemente como "Sistema:".
- Incluye timestamps al inicio de cada cambio de hablante en formato [MM:SS].
- Transcribe exactamente lo que se dice, incluyendo muletillas (eh, este, mmm), repeticiones y errores gramaticales.
- IMPORTANTE: Evita "alucinaciones" de repetición infinita. Si el audio tiene silencio o ruido, no repitas la última frase indefinidamente.
- Si hay pausas largas, indica [pausa]. Si algo es ininteligible, indica [inaudible].
- El idioma principal es español latinoamericano.

TAREA 2 - ANÁLISIS EMOCIONAL Y ACÚSTICO:
Analiza la llamada por tramos usando lo que se escucha en el audio y lo que se dice en la transcripción.
- Considera sentimiento, emoción, tono de voz, ritmo/velocidad, volumen aparente, claridad, pausas relevantes, tensión, interrupciones y manejo emocional.
- Genera máximo 24 tramos representativos alineados con toda la transcripción; agrupa intervenciones consecutivas con la misma emoción. Usa "index" empezando en 0.
- Usa "start" cuando puedas estimar el segundo real del tramo; si no, usa el timestamp de la intervención.
- No inventes datos imposibles. Si una señal acústica no se aprecia, marca "no_detectado" o usa un score conservador.
- El análisis emocional es señal de apoyo para calidad; no reemplaza la evidencia textual.

TAREA 3 - INDICADORES PARA FEEDBACK OPERATIVO:
Genera indicadores accionables parecidos a call analytics, pero usando solo este proveedor y el audio disponible.
- Enfócate en feedback útil para un monitor: qué hizo bien el agente, qué debe corregir, en qué segundo ocurrió y por qué impacta la experiencia.
- Evalúa recuperación emocional: si el cliente inició negativo o tenso, indica si el agente logró contener, recuperar o empeorar la llamada.
- Evalúa objeciones: identifica si el cliente rechazó, dudó, reclamó, pidió postergar, mostró molestia o quedó sin solución.
- Evalúa cierre: confirma si el final queda claro, cordial, con siguiente paso definido o con riesgo.
- Los campos de listas deben tener máximo 4 elementos. Cada elemento debe ser breve y accionable.

RESPONDE EXCLUSIVAMENTE con el siguiente JSON válido.
IMPORTANTE: El campo "transcript" debe ser un solo string con saltos de línea (\n) separando cada intervención.

{
  "transcript": "[00:00] Agente: Hola, ¿en qué puedo ayudarle?\n[00:05] Cliente: Hola, tengo un problema...",
  "sentiment": {
    "overall": "positivo|neutro|negativo|mixto",
    "overall_score": 0.0,
    "agent": {
      "sentiment": "positivo|neutro|negativo",
      "score": 0.0,
      "tone": "descripción breve del tono del agente",
      "pace": "pausado|normal|rápido|variable|no_detectado",
      "energy": "baja|media|alta|variable|no_detectado"
    },
    "client": {
      "sentiment": "positivo|neutro|negativo",
      "score": 0.0,
      "tone": "descripción breve del tono del cliente",
      "pace": "pausado|normal|rápido|variable|no_detectado",
      "energy": "baja|media|alta|variable|no_detectado",
      "satisfaction": "satisfecho|insatisfecho|neutro"
    },
    "summary": "Resumen breve del sentimiento general de la llamada en 1-2 oraciones."
  },
  "sentiment_segments": [
    {
      "index": 0,
      "start": 0,
      "speaker": "agent|client|system",
      "sentiment": "positivo|neutro|negativo|mixto",
      "emotion": "calma|confianza|satisfaccion|preocupacion|frustracion|tension_controlada|enojo|tristeza|molestia",
      "score": 0.0,
      "intensity": 0,
      "tone": "tono percibido en pocas palabras",
      "pace": "pausado|normal|rápido|variable|no_detectado",
      "volume": "bajo|medio|alto|variable|no_detectado",
      "clarity": "claro|regular|bajo|no_detectado",
      "evidence": "frase corta o señal que justifica el tramo"
    }
  ],
  "acoustic_analysis": {
    "agent_speech_rate_wpm": 0,
    "client_speech_rate_wpm": 0,
    "overall_pace": "pausado|normal|rápido|variable|no_detectado",
    "agent_energy": "baja|media|alta|variable|no_detectado",
    "client_energy": "baja|media|alta|variable|no_detectado",
    "clarity": "claro|regular|bajo|no_detectado",
    "interruptions": 0,
    "agent_interruptions": 0,
    "client_interruptions": 0,
    "long_pauses": 0,
    "silence_ratio": 0.0,
    "talk_balance": "agent_dominant|client_dominant|balanced|no_detectado",
    "talk_balance_note": "lectura breve del balance de conversación",
    "emotional_turning_point": {
      "second": 0,
      "label": "MM:SS",
      "type": "recuperacion|deterioro|sin_cambio|no_detectado",
      "summary": "momento donde cambia o se estabiliza la emoción"
    },
    "notes": "observaciones acústicas relevantes"
  },
  "quality_signals": {
    "empathy": "fortaleza|neutral|riesgo",
    "active_listening": "fortaleza|neutral|riesgo",
    "objection_handling": "fortaleza|neutral|riesgo",
    "resolution_clarity": "fortaleza|neutral|riesgo",
    "script_control": "fortaleza|neutral|riesgo",
    "closing_quality": "fortaleza|neutral|riesgo",
    "customer_experience_risk": "bajo|medio|alto",
    "emotional_recovery": "recupera|contiene|empeora|sin_riesgo|no_detectado",
    "agent_control": "alto|medio|bajo|no_detectado",
    "frustration_cause": "motivo principal de frustración o no_detectado",
    "customer_left_unresolved": true,
    "supervisor_alerts": [
      {
        "level": "alto|medio|bajo",
        "second": 0,
        "label": "MM:SS",
        "message": "alerta operativa breve"
      }
    ],
    "critical_moments": [
      {
        "second": 0,
        "label": "MM:SS",
        "speaker": "agent|client|system",
        "type": "riesgo|fortaleza|oportunidad",
        "title": "nombre corto del momento",
        "evidence": "frase o señal concreta",
        "feedback": "qué debe observar o corregir el monitor"
      }
    ],
    "coaching_recommendations": [
      {
        "priority": "alta|media|baja",
        "skill": "empatía|escucha activa|objeciones|claridad|cierre|control emocional|producto",
        "recommendation": "recomendación concreta para el agente",
        "example": "frase sugerida o conducta observable"
      }
    ],
    "summary": "cómo estas señales impactan calidad y experiencia del cliente"
  }
}

NOTAS sobre sentiment scores: Usa una escala de -1.0 (muy negativo) a 1.0 (muy positivo), donde 0.0 es neutro.
NOTAS sobre intensity: Usa 0 a 100, donde 0 es sin carga emocional y 100 es carga muy alta.
PROMPT;

            $prompt .= $this->technicalSilencePrompt($technicalSilence);

            $systemInstruction = 'Eres un transcriptor profesional de audio y analista de sentimiento para un centro de contacto (call center). Tu tarea requiere EXTREMA PRECISIÓN y ESTRICTO APEGO a las instrucciones de formato. DEBES devolver UNICAMENTE un objeto JSON válido.';

            $response = Http::timeout(300)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemInstruction],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Audio,
                                    ],
                                ],
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'topP' => 0.1,
                        'topK' => 1,
                        'maxOutputTokens' => 16384,
                        'responseMimeType' => 'application/json',
                        'thinkingConfig' => [
                            'thinkingBudget' => 0,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json('error.message', $response->body());
                Log::error("AudioTranscriptionService: Gemini API error - {$error}");
                throw AiProviderErrors::exceptionFor('gemini', $response->status(), "Gemini API error: {$error}");
            }

            $result = $response->json();
            $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $rawText = trim($rawText);

            if (empty($rawText)) {
                throw new \Exception('Gemini returned an empty response.');
            }

            $rawText = $this->normalizeGeminiText($rawText);
            $parsed = $this->parseJsonPayload($rawText);

            if (! is_array($parsed) || ! isset($parsed['transcript'])) {
                $cleanTranscript = $this->extractTranscriptFallback($parsed, $rawText);

                if ($cleanTranscript === null) {
                    throw new TransientAiProviderException(
                        'Gemini did not return valid transcription JSON with required audio analysis.',
                        'gemini'
                    );
                }

                Log::warning('AudioTranscriptionService: Could not parse full JSON; running second-pass audio analysis');

                $analysis = $this->analyzeAudioSignals(
                    $base64Audio,
                    $mimeType,
                    $cleanTranscript,
                    $technicalSilence
                );

                return array_merge($analysis, [
                    'transcript' => $cleanTranscript,
                    'duration_seconds' => $audioDurationSeconds ?? $this->durationSecondsFromTranscript($cleanTranscript),
                    'provider' => 'gemini',
                    'model' => $this->model,
                ]);
            }

            // Clean literal \n if they survived in the transcript field
            $cleanTranscript = str_replace('\n', "\n", $parsed['transcript']);
            $parsedAcousticAnalysis = is_array($parsed['acoustic_analysis'] ?? null) ? $parsed['acoustic_analysis'] : [];
            $acousticAnalysis = $this->mergeTechnicalSilenceAnalysis($parsedAcousticAnalysis, $technicalSilence);
            $initialAnalysis = [
                'sentiment' => $parsed['sentiment'] ?? null,
                'sentiment_segments' => $parsed['sentiment_segments'] ?? [],
                'acoustic_analysis' => $acousticAnalysis,
                'quality_signals' => $parsed['quality_signals'] ?? null,
            ];

            if (! $this->hasRequiredAudioAnalysis($initialAnalysis)) {
                Log::warning('AudioTranscriptionService: Transcription JSON missing required analysis; running second-pass audio analysis');

                $analysis = $this->analyzeAudioSignals(
                    $base64Audio,
                    $mimeType,
                    $cleanTranscript,
                    $technicalSilence,
                    $initialAnalysis
                );

                $parsed['sentiment'] = $analysis['sentiment'];
                $parsed['sentiment_segments'] = $analysis['sentiment_segments'];
                $acousticAnalysis = $analysis['acoustic_analysis'];
                $parsed['quality_signals'] = $analysis['quality_signals'];
            }

            Log::info('AudioTranscriptionService: Transcription + sentiment completed. Length: '.strlen($cleanTranscript).' chars');

            return [
                'transcript' => $cleanTranscript,
                'sentiment' => $parsed['sentiment'] ?? null,
                'sentiment_segments' => $parsed['sentiment_segments'] ?? [],
                'acoustic_analysis' => $acousticAnalysis,
                'quality_signals' => $parsed['quality_signals'] ?? null,
                'duration_seconds' => $audioDurationSeconds ?? $this->durationSecondsFromTranscript($cleanTranscript),
                'provider' => 'gemini',
                'model' => $this->model,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $safeMessage = AiProviderErrors::sanitize($e->getMessage());

            Log::error('AudioTranscriptionService: Connection error', [
                'error' => $safeMessage,
            ]);

            throw AiProviderErrors::exceptionFor('gemini', null, 'Could not connect to Gemini API: '.$safeMessage);
        }
    }

    private function normalizeGeminiText(string $rawText): string
    {
        $rawText = $this->cleanHallucinatedLoops($rawText);
        $rawText = preg_replace('/^```(?:json)?\s*/m', '', $rawText) ?? $rawText;
        $rawText = preg_replace('/\s*```$/m', '', $rawText) ?? $rawText;

        return trim($rawText);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonPayload(string $rawText): ?array
    {
        $parsed = json_decode($rawText, true);

        if (json_last_error() !== JSON_ERROR_NONE
            && preg_match('/\{(?:[^{}]|(?R))*\}/s', $rawText, $matches)) {
            $parsed = json_decode(trim($matches[0]), true);
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param  array<string, mixed>|null  $parsed
     */
    private function extractTranscriptFallback(?array $parsed, string $rawText): ?string
    {
        if (isset($parsed['transcript']) && is_string($parsed['transcript'])) {
            return str_replace('\n', "\n", trim($parsed['transcript']));
        }

        if (preg_match('/"transcript"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $rawText, $matches)) {
            $decoded = json_decode('"'.$matches[1].'"');

            if (is_string($decoded) && trim($decoded) !== '') {
                return str_replace('\n', "\n", trim($decoded));
            }
        }

        $candidate = trim($rawText);

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/\b(Agente|Cliente|Sistema)\s*:/iu', $candidate)
            || preg_match('/\[?\d{1,2}:\d{2}(?::\d{2})?\]?/', $candidate)) {
            return str_replace('\n', "\n", $candidate);
        }

        if (str_starts_with($candidate, '{') || str_starts_with($candidate, '[')) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $technicalSilence
     * @return array<string, mixed>
     */
    private function analyzeAudioSignals(
        string $base64Audio,
        string $mimeType,
        string $transcript,
        array $technicalSilence,
        array $existingAnalysis = [],
        bool $allowFocusedRetry = true
    ): array {
        $transcriptContext = mb_strlen($transcript) > 60000
            ? mb_substr($transcript, 0, 60000)."\n\n[Transcripción truncada para análisis por longitud]"
            : $transcript;

        $missingFields = $this->missingRequiredAudioAnalysis($existingAnalysis);
        $segmentLimit = $allowFocusedRetry ? 24 : 12;
        $template = json_encode(
            $this->audioAnalysisTemplate($missingFields),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        $prompt = 'Tenemos la transcripcion, pero faltan estas secciones obligatorias: '.implode(', ', $missingFields).".\n"
            ."Analiza nuevamente el AUDIO y usa la transcripcion como apoyo. Devuelve SOLO JSON valido y exclusivamente las claves del formato indicado.\n"
            ."Genera entre 1 y {$segmentLimit} tramos emocionales representativos de toda la llamada; agrupa estados consecutivos y limita cada evidencia a 160 caracteres.\n"
            ."Si una senal no se aprecia, usa no_detectado. Las listas operativas deben tener maximo 4 elementos.\n"
            ."Formato obligatorio:\n{$template}\n\nTRANSCRIPCION:\n{$transcriptContext}";
        $prompt .= $this->technicalSilencePrompt($technicalSilence);

        $response = Http::timeout($allowFocusedRetry ? 180 : 120)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => 'Eres un analista de sentimiento y señales acústicas de call center. Devuelve únicamente JSON válido.'],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Audio,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'topP' => 0.1,
                    'topK' => 1,
                    'maxOutputTokens' => 12000,
                    'responseMimeType' => 'application/json',
                    'thinkingConfig' => [
                        'thinkingBudget' => 0,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            Log::error("AudioTranscriptionService: Gemini analysis repair error - {$error}");
            throw AiProviderErrors::exceptionFor('gemini', $response->status(), "Gemini analysis repair error: {$error}");
        }

        $rawText = trim($response->json('candidates.0.content.parts.0.text', ''));

        if ($rawText === '') {
            if ($allowFocusedRetry) {
                Log::warning('AudioTranscriptionService: Empty repair response; running focused retry', [
                    'finish_reason' => $response->json('candidates.0.finishReason'),
                    'missing_fields' => $this->missingRequiredAudioAnalysis($existingAnalysis),
                ]);

                return $this->analyzeAudioSignals(
                    $base64Audio,
                    $mimeType,
                    $transcript,
                    $technicalSilence,
                    $existingAnalysis,
                    false
                );
            }

            throw new TransientAiProviderException('Gemini returned an empty audio analysis response.', 'gemini');
        }

        $parsed = $this->parseJsonPayload($this->normalizeGeminiText($rawText)) ?? [];

        $generatedAnalysis = [
            'sentiment' => $parsed['sentiment'] ?? null,
            'sentiment_segments' => $parsed['sentiment_segments'] ?? [],
            'acoustic_analysis' => is_array($parsed['acoustic_analysis'] ?? null) ? $parsed['acoustic_analysis'] : [],
            'quality_signals' => $parsed['quality_signals'] ?? null,
        ];
        $analysis = $this->mergeAudioAnalysis($existingAnalysis, $generatedAnalysis, $technicalSilence);

        if (! $this->hasRequiredAudioAnalysis($analysis)) {
            $missingFields = $this->missingRequiredAudioAnalysis($analysis);

            Log::warning('AudioTranscriptionService: Repair response incomplete', [
                'finish_reason' => $response->json('candidates.0.finishReason'),
                'response_chars' => strlen($rawText),
                'missing_fields' => $missingFields,
                'focused_retry' => ! $allowFocusedRetry,
            ]);

            if ($allowFocusedRetry) {
                return $this->analyzeAudioSignals(
                    $base64Audio,
                    $mimeType,
                    $transcript,
                    $technicalSilence,
                    $analysis,
                    false
                );
            }

            throw new TransientAiProviderException(
                'Gemini audio analysis response is missing required fields: '.implode(', ', $missingFields).'.',
                'gemini'
            );
        }

        return $analysis;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function audioAnalysisTemplate(array $fields): array
    {
        $templates = [
            'sentiment' => [
                'overall' => 'positivo|neutro|negativo|mixto',
                'overall_score' => 0.0,
                'agent' => [
                    'sentiment' => 'positivo|neutro|negativo',
                    'score' => 0.0,
                    'tone' => 'descripcion breve',
                    'pace' => 'pausado|normal|rapido|variable|no_detectado',
                    'energy' => 'baja|media|alta|variable|no_detectado',
                ],
                'client' => [
                    'sentiment' => 'positivo|neutro|negativo',
                    'score' => 0.0,
                    'tone' => 'descripcion breve',
                    'pace' => 'pausado|normal|rapido|variable|no_detectado',
                    'energy' => 'baja|media|alta|variable|no_detectado',
                    'satisfaction' => 'satisfecho|insatisfecho|neutro',
                ],
                'summary' => 'resumen breve',
            ],
            'sentiment_segments' => [[
                'index' => 0,
                'start' => 0,
                'speaker' => 'agent|client|system',
                'sentiment' => 'positivo|neutro|negativo|mixto',
                'emotion' => 'calma|confianza|satisfaccion|preocupacion|frustracion|tension_controlada|enojo|tristeza|molestia',
                'score' => 0.0,
                'intensity' => 0,
                'tone' => 'tono breve',
                'pace' => 'pausado|normal|rapido|variable|no_detectado',
                'volume' => 'bajo|medio|alto|variable|no_detectado',
                'clarity' => 'claro|regular|bajo|no_detectado',
                'evidence' => 'evidencia breve',
            ]],
            'acoustic_analysis' => [
                'agent_speech_rate_wpm' => 0,
                'client_speech_rate_wpm' => 0,
                'overall_pace' => 'pausado|normal|rapido|variable|no_detectado',
                'agent_energy' => 'baja|media|alta|variable|no_detectado',
                'client_energy' => 'baja|media|alta|variable|no_detectado',
                'clarity' => 'claro|regular|bajo|no_detectado',
                'interruptions' => 0,
                'agent_interruptions' => 0,
                'client_interruptions' => 0,
                'long_pauses' => 0,
                'silence_ratio' => 0.0,
                'talk_balance' => 'agent_dominant|client_dominant|balanced|no_detectado',
                'talk_balance_note' => 'lectura breve',
                'emotional_turning_point' => [
                    'second' => 0,
                    'label' => 'MM:SS',
                    'type' => 'recuperacion|deterioro|sin_cambio|no_detectado',
                    'summary' => 'cambio emocional breve',
                ],
                'notes' => 'observaciones breves',
            ],
            'quality_signals' => [
                'empathy' => 'fortaleza|neutral|riesgo',
                'active_listening' => 'fortaleza|neutral|riesgo',
                'objection_handling' => 'fortaleza|neutral|riesgo',
                'resolution_clarity' => 'fortaleza|neutral|riesgo',
                'script_control' => 'fortaleza|neutral|riesgo',
                'closing_quality' => 'fortaleza|neutral|riesgo',
                'customer_experience_risk' => 'bajo|medio|alto',
                'emotional_recovery' => 'recupera|contiene|empeora|sin_riesgo|no_detectado',
                'agent_control' => 'alto|medio|bajo|no_detectado',
                'frustration_cause' => 'motivo o no_detectado',
                'customer_left_unresolved' => true,
                'supervisor_alerts' => [],
                'critical_moments' => [],
                'coaching_recommendations' => [],
                'summary' => 'impacto breve en calidad y experiencia',
            ],
        ];

        return array_intersect_key($templates, array_flip($fields));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function hasRequiredAudioAnalysis(array $result): bool
    {
        return $this->missingRequiredAudioAnalysis($result) === [];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function missingRequiredAudioAnalysis(array $result): array
    {
        return collect(['sentiment', 'sentiment_segments', 'acoustic_analysis', 'quality_signals'])
            ->filter(fn (string $key) => ! is_array($result[$key] ?? null) || empty($result[$key]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $generated
     * @param  array<string, mixed>  $technicalSilence
     * @return array<string, mixed>
     */
    private function mergeAudioAnalysis(array $existing, array $generated, array $technicalSilence): array
    {
        $merged = [];

        foreach (['sentiment', 'sentiment_segments', 'acoustic_analysis', 'quality_signals'] as $key) {
            $generatedValue = $generated[$key] ?? null;
            $existingValue = $existing[$key] ?? null;
            $merged[$key] = is_array($generatedValue) && ! empty($generatedValue)
                ? $generatedValue
                : $existingValue;
        }

        $merged['acoustic_analysis'] = $this->mergeTechnicalSilenceAnalysis(
            is_array($merged['acoustic_analysis'] ?? null) ? $merged['acoustic_analysis'] : [],
            $technicalSilence
        );

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $technicalSilence
     */
    private function technicalSilencePrompt(array $technicalSilence): string
    {
        if (($technicalSilence['source'] ?? 'unavailable') === 'unavailable') {
            return '';
        }

        $payload = [
            'detected_by' => $technicalSilence['source'] ?? null,
            'threshold_db' => $technicalSilence['threshold_db'] ?? null,
            'minimum_silence_seconds' => $technicalSilence['minimum_silence_seconds'] ?? null,
            'long_pauses' => $technicalSilence['long_pauses'] ?? 0,
            'total_silence_seconds' => $technicalSilence['total_silence_seconds'] ?? 0,
            'silence_ratio' => $technicalSilence['silence_ratio'] ?? 0,
            'longest_silence_seconds' => $technicalSilence['longest_silence_seconds'] ?? 0,
            'longest_silence_label' => $technicalSilence['longest_silence_label'] ?? null,
            'segments' => collect($technicalSilence['segments'] ?? [])
                ->take(8)
                ->values()
                ->all(),
        ];

        return "\n\nDATOS TECNICOS DE SILENCIO DETECTADOS POR EL SISTEMA:\n"
            .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ."\nUsa estos datos técnicos para long_pauses, silence_ratio, riesgo operativo y feedback. No reemplaces estos valores por estimaciones si el audio tiene silencios detectados.\n";
    }

    /**
     * @param  array<string, mixed>|null  $acousticAnalysis
     * @param  array<string, mixed>  $technicalSilence
     * @return array<string, mixed>
     */
    private function mergeTechnicalSilenceAnalysis(?array $acousticAnalysis, array $technicalSilence): array
    {
        $acousticAnalysis ??= [];

        if (($technicalSilence['source'] ?? 'unavailable') === 'unavailable') {
            return $acousticAnalysis;
        }

        $acousticAnalysis['long_pauses'] = (int) ($technicalSilence['long_pauses'] ?? 0);
        $acousticAnalysis['silence_ratio'] = (float) ($technicalSilence['silence_ratio'] ?? 0.0);
        $acousticAnalysis['dead_air_total_seconds'] = (float) ($technicalSilence['total_silence_seconds'] ?? 0.0);
        $acousticAnalysis['dead_air_total_label'] = $technicalSilence['total_silence_label'] ?? '00:00';
        $acousticAnalysis['dead_air_longest_seconds'] = (float) ($technicalSilence['longest_silence_seconds'] ?? 0.0);
        $acousticAnalysis['dead_air_longest_label'] = $technicalSilence['longest_silence_label'] ?? null;
        $acousticAnalysis['dead_air_segments'] = $technicalSilence['segments'] ?? [];
        $acousticAnalysis['dead_air_detected_by'] = $technicalSilence['source'] ?? null;
        $acousticAnalysis['dead_air_threshold_db'] = $technicalSilence['threshold_db'] ?? null;
        $acousticAnalysis['dead_air_minimum_seconds'] = $technicalSilence['minimum_silence_seconds'] ?? null;

        return $acousticAnalysis;
    }

    /**
     * Detects and removes long sequences of repeated words/phrases (hallucinations).
     */
    protected function cleanHallucinatedLoops(string $text): string
    {
        // Detects when a sequence of 5 to 30 characters repeats more than 4 times
        return preg_replace('/(\b.{5,30}?\b)(?:\s+\1){4,}/i', '$1', $text);
    }

    /**
     * Get the MIME type for an audio file.
     */
    protected function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'mpeg', 'mpga' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg', 'oga', 'opus' => 'audio/ogg',
            'm4a', 'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            'webm' => 'audio/webm',
            'flac' => 'audio/flac',
            default => 'audio/mpeg',
        };
    }

    protected function durationSecondsFromAudioBytes(string $audioData, string $filePath): ?int
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension !== 'wav' || strlen($audioData) < 44) {
            return null;
        }

        if (substr($audioData, 0, 4) !== 'RIFF' || substr($audioData, 8, 4) !== 'WAVE') {
            return null;
        }

        $offset = 12;
        $format = null;
        $sampleRate = null;
        $byteRate = null;
        $dataSize = null;
        $factSampleCount = null;
        $length = strlen($audioData);

        while ($offset + 8 <= $length) {
            $chunkId = substr($audioData, $offset, 4);
            $chunkSize = unpack('V', substr($audioData, $offset + 4, 4))[1] ?? 0;
            $chunkDataOffset = $offset + 8;

            if ($chunkId === 'fmt ' && $chunkSize >= 16 && $chunkDataOffset + 16 <= $length) {
                $fmt = unpack('vformat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample', substr($audioData, $chunkDataOffset, 16));
                $format = (int) ($fmt['format'] ?? 0);
                $sampleRate = (int) ($fmt['sampleRate'] ?? 0);
                $byteRate = (int) ($fmt['byteRate'] ?? 0);
            }

            if ($chunkId === 'fact' && $chunkSize >= 4 && $chunkDataOffset + 4 <= $length) {
                $factSampleCount = (int) (unpack('V', substr($audioData, $chunkDataOffset, 4))[1] ?? 0);
            }

            if ($chunkId === 'data') {
                $dataSize = (int) $chunkSize;
            }

            if ($byteRate && $dataSize && ($format === 1 || $factSampleCount || ! $sampleRate)) {
                break;
            }

            $offset = $chunkDataOffset + $chunkSize + ($chunkSize % 2);
        }

        $byteRateDuration = ($byteRate && $dataSize)
            ? max(1, (int) round($dataSize / $byteRate))
            : null;

        if ($format !== null && $format !== 1 && $factSampleCount && $sampleRate) {
            $factDuration = max(1, (int) round($factSampleCount / $sampleRate));

            if ($byteRateDuration && abs($factDuration - $byteRateDuration) > max(2, (int) round($byteRateDuration * 0.25))) {
                return $byteRateDuration;
            }

            return $factDuration;
        }

        if (! $byteRateDuration) {
            return null;
        }

        return $byteRateDuration;
    }

    protected function durationSecondsFromTranscript(string $transcript): ?int
    {
        preg_match_all('/\[?(\d{1,2}):(\d{2})(?::(\d{2}))?\]?/', $transcript, $matches, PREG_SET_ORDER);

        $maxSeconds = null;

        foreach ($matches as $match) {
            if (isset($match[3]) && $match[3] !== '') {
                $seconds = ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) $match[3];
            } else {
                $seconds = ((int) $match[1] * 60) + (int) $match[2];
            }

            $maxSeconds = max($maxSeconds ?? 0, $seconds);
        }

        return $maxSeconds ? $maxSeconds : null;
    }

    /**
     * Simulate transcription for development/testing when no API key is available.
     */
    protected function simulateTranscription(string $filePath): array
    {
        $fileName = basename($filePath);
        $transcript = implode("\n", [
            '[00:00] Agente: Buenos días, gracias por comunicarse con nosotros. Mi nombre es María, ¿en qué puedo ayudarle?',
            '[00:04] Cliente: Hola María, buenos días. Llamo porque tengo un problema con mi factura del mes pasado.',
            '[00:10] Agente: Entiendo, lamento mucho el inconveniente. Permítame verificar su cuenta. ¿Me puede proporcionar su número de identificación o el número de cuenta?',
            '[00:18] Cliente: Sí, claro. Mi número de cuenta es 4523-8891.',
            '[00:22] Agente: Perfecto, ya tengo su cuenta en pantalla. Veo que efectivamente hay un cargo adicional de $15.000 que parece ser un cobro duplicado. ¿Es ese el problema que menciona?',
            '[00:32] Cliente: Exactamente, ese cargo no debería estar ahí.',
            '[00:35] Agente: Tiene toda la razón, señor. Voy a proceder a generar una nota de crédito por ese monto. El ajuste se verá reflejado en su próxima factura. ¿Hay algo más en que pueda ayudarle?',
            '[00:45] Cliente: No, eso era todo. Muchas gracias por la rápida solución.',
            '[00:49] Agente: Con mucho gusto. Que tenga un excelente día.',
            '[00:52] Cliente: Igualmente, hasta luego.',
        ]);

        Log::info("AudioTranscriptionService: Simulated transcription for {$fileName}");

        $durationSeconds = $this->durationSecondsFromStoredAudio($filePath) ?? $this->durationSecondsFromTranscript($transcript);
        $technicalSilence = $this->silenceAnalysisService->analyzeStoredFile($filePath, $durationSeconds);
        $acousticAnalysis = $this->mergeTechnicalSilenceAnalysis([
            'agent_speech_rate_wpm' => 132,
            'client_speech_rate_wpm' => 118,
            'overall_pace' => 'normal',
            'agent_energy' => 'media',
            'client_energy' => 'media',
            'clarity' => 'claro',
            'interruptions' => 0,
            'agent_interruptions' => 0,
            'client_interruptions' => 0,
            'long_pauses' => 0,
            'silence_ratio' => 0.04,
            'talk_balance' => 'balanced',
            'talk_balance_note' => 'El agente guía la llamada sin desplazar la participación del cliente.',
            'emotional_turning_point' => [
                'second' => 35,
                'label' => '00:35',
                'type' => 'recuperacion',
                'summary' => 'El cliente pasa de preocupación a conformidad cuando el agente confirma la nota de crédito.',
            ],
            'notes' => 'Ritmo estable, buena claridad y cierre sin tensión audible.',
        ], $technicalSilence);

        return [
            'transcript' => $transcript,
            'duration_seconds' => $durationSeconds,
            'sentiment' => [
                'overall' => 'positivo',
                'overall_score' => 0.7,
                'agent' => [
                    'sentiment' => 'positivo',
                    'score' => 0.8,
                    'tone' => 'Profesional, empático y resolutivo',
                    'pace' => 'normal',
                    'energy' => 'media',
                ],
                'client' => [
                    'sentiment' => 'positivo',
                    'score' => 0.6,
                    'tone' => 'Inicialmente preocupado, luego satisfecho con la resolución',
                    'pace' => 'normal',
                    'energy' => 'media',
                    'satisfaction' => 'satisfecho',
                ],
                'summary' => 'Llamada positiva donde el agente resolvió de manera eficiente un problema de cobro duplicado. El cliente pasó de preocupado a satisfecho.',
            ],
            'sentiment_segments' => [
                ['index' => 0, 'start' => 0, 'speaker' => 'agent', 'sentiment' => 'positivo', 'emotion' => 'confianza', 'score' => 0.6, 'intensity' => 45, 'tone' => 'cordial y claro', 'pace' => 'normal', 'volume' => 'medio', 'clarity' => 'claro', 'evidence' => 'saludo inicial amable'],
                ['index' => 1, 'start' => 4, 'speaker' => 'client', 'sentiment' => 'mixto', 'emotion' => 'preocupacion', 'score' => -0.2, 'intensity' => 62, 'tone' => 'preocupado pero colaborativo', 'pace' => 'normal', 'volume' => 'medio', 'clarity' => 'claro', 'evidence' => 'problema con factura'],
                ['index' => 2, 'start' => 10, 'speaker' => 'agent', 'sentiment' => 'positivo', 'emotion' => 'tension_controlada', 'score' => 0.5, 'intensity' => 58, 'tone' => 'empático y de contención', 'pace' => 'normal', 'volume' => 'medio', 'clarity' => 'claro', 'evidence' => 'lamenta el inconveniente y verifica cuenta'],
                ['index' => 7, 'start' => 45, 'speaker' => 'client', 'sentiment' => 'positivo', 'emotion' => 'satisfaccion', 'score' => 0.7, 'intensity' => 50, 'tone' => 'agradecido', 'pace' => 'normal', 'volume' => 'medio', 'clarity' => 'claro', 'evidence' => 'agradece la rápida solución'],
            ],
            'acoustic_analysis' => $acousticAnalysis,
            'quality_signals' => [
                'empathy' => 'fortaleza',
                'active_listening' => 'fortaleza',
                'objection_handling' => 'fortaleza',
                'resolution_clarity' => 'fortaleza',
                'script_control' => 'fortaleza',
                'closing_quality' => 'fortaleza',
                'customer_experience_risk' => 'bajo',
                'emotional_recovery' => 'recupera',
                'agent_control' => 'alto',
                'frustration_cause' => 'cargo duplicado en factura',
                'customer_left_unresolved' => false,
                'supervisor_alerts' => [],
                'critical_moments' => [
                    [
                        'second' => 10,
                        'label' => '00:10',
                        'speaker' => 'agent',
                        'type' => 'fortaleza',
                        'title' => 'Contención inicial',
                        'evidence' => 'Lamenta el inconveniente y pide verificar la cuenta.',
                        'feedback' => 'Mantener reconocimiento del problema antes de pasar a validación.',
                    ],
                    [
                        'second' => 35,
                        'label' => '00:35',
                        'speaker' => 'agent',
                        'type' => 'fortaleza',
                        'title' => 'Resolución clara',
                        'evidence' => 'Confirma la nota de crédito y el efecto en la próxima factura.',
                        'feedback' => 'Buen cierre de solución porque explica acción y expectativa.',
                    ],
                ],
                'coaching_recommendations' => [
                    [
                        'priority' => 'baja',
                        'skill' => 'cierre',
                        'recommendation' => 'Cerrar confirmando si el cliente entendió el ajuste y el plazo.',
                        'example' => '¿Le quedó claro cuándo verá reflejada la nota de crédito?',
                    ],
                ],
                'summary' => 'El tono empático y la resolución reducen el riesgo de experiencia negativa.',
            ],
        ];
    }

    private function durationSecondsFromStoredAudio(string $filePath): ?int
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($filePath)) {
            return null;
        }

        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'wav') {
            $durationFromBytes = $this->durationSecondsFromAudioBytes($disk->get($filePath), $filePath);

            if ($durationFromBytes) {
                return $durationFromBytes;
            }
        }

        if (method_exists($disk, 'path')) {
            $durationFromProbe = $this->durationSecondsFromMediaProbe($disk->path($filePath));

            if ($durationFromProbe) {
                return $durationFromProbe;
            }
        }

        return $this->durationSecondsFromAudioBytes($disk->get($filePath), $filePath);
    }

    private function durationSecondsFromMediaProbe(string $absolutePath): ?int
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $binary = config('services.ffprobe.path')
            ?: env('FFPROBE_PATH')
            ?: (new ExecutableFinder)->find('ffprobe');

        if (! $binary) {
            return null;
        }

        try {
            $process = new Process([
                $binary,
                '-v',
                'error',
                '-show_entries',
                'format=duration',
                '-of',
                'default=noprint_wrappers=1:nokey=1',
                $absolutePath,
            ]);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $duration = (float) trim($process->getOutput());

            return $duration > 0 ? max(1, (int) round($duration)) : null;
        } catch (\Throwable $e) {
            Log::debug('AudioTranscriptionService: ffprobe duration detection failed', [
                'file' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
