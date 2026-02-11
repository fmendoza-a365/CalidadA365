<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioTranscriptionService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = Setting::get('ai.gemini_api_key', config('ai.gemini.api_key', '')) ?? '';
        $this->model = 'gemini-2.5-flash'; // Correct model name from API list
    }

    /**
     * Transcribe an audio file and analyze sentiment using Google Gemini API.
     *
     * @param string $filePath Path relative to the storage disk
     * @param string $language Language hint (default: Spanish)
     * @return array{transcript: string, sentiment: array} Transcript text and sentiment analysis
     * @throws \Exception If transcription fails
     */
    public function transcribe(string $filePath, string $language = 'es'): array
    {
        if (empty($this->apiKey)) {
            Log::warning('AudioTranscriptionService: No Gemini API key configured, using simulated transcription.');
            return $this->simulateTranscription($filePath);
        }

        $absolutePath = Storage::disk('local')->path($filePath);

        if (!file_exists($absolutePath)) {
            throw new \Exception("Audio file not found: {$absolutePath}");
        }

        Log::info("AudioTranscriptionService: Transcribing file {$filePath} with Gemini");

        try {
            $audioData = file_get_contents($absolutePath);
            $base64Audio = base64_encode($audioData);
            $mimeType = $this->getMimeType($filePath);

            $prompt = <<<'PROMPT'
Eres un transcriptor profesional de audio y analista de sentimiento para un centro de contacto (call center).

TAREA 1 - TRANSCRIPCIÓN:
- Transcribe el audio completo palabra por palabra, sin omitir nada.
- Identifica y diferencia a cada hablante. Usa "Agente:" para el representante y "Cliente:" para quien llama.
- Incluye timestamps al inicio de cada cambio de hablante en formato [MM:SS].
- Transcribe exactamente lo que se dice, incluyendo muletillas (eh, este, mmm), repeticiones y errores gramaticales.
- Si hay pausas largas, indica [pausa]. Si algo es ininteligible, indica [inaudible].
- El idioma principal es español latinoamericano.

TAREA 2 - ANÁLISIS DE SENTIMIENTO:
Analiza el sentimiento general de la llamada y de cada participante.

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
      "tone": "descripción breve del tono del agente"
    },
    "client": {
      "sentiment": "positivo|neutro|negativo",
      "score": 0.0,
      "tone": "descripción breve del tono del cliente",
      "satisfaction": "satisfecho|insatisfecho|neutro"
    },
    "summary": "Resumen breve del sentimiento general de la llamada en 1-2 oraciones."
  }
}

NOTAS sobre sentiment scores: Usa una escala de -1.0 (muy negativo) a 1.0 (muy positivo), donde 0.0 es neutro.
PROMPT;

            $response = Http::timeout(300)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
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
                        'maxOutputTokens' => 16384,
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json('error.message', $response->body());
                Log::error("AudioTranscriptionService: Gemini API error - {$error}");
                throw new \Exception("Gemini API error: {$error}");
            }

            $result = $response->json();
            $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $rawText = trim($rawText);

            if (empty($rawText)) {
                throw new \Exception('Gemini returned an empty response.');
            }

            // Clean markdown code fences if present
            $rawText = preg_replace('/^```(?:json)?\s*/m', '', $rawText);
            $rawText = preg_replace('/\s*```$/m', '', $rawText);
            $rawText = trim($rawText);

            $parsed = json_decode($rawText, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['transcript'])) {
                // Fallback: treat the entire response as plain transcript text
                Log::warning("AudioTranscriptionService: Could not parse JSON, using raw text as transcript");
                return [
                    'transcript' => $rawText,
                    'sentiment' => null,
                ];
            }

            Log::info("AudioTranscriptionService: Transcription + sentiment completed. Length: " . strlen($parsed['transcript']) . " chars");

            return [
                'transcript' => $parsed['transcript'],
                'sentiment' => $parsed['sentiment'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("AudioTranscriptionService: Connection error - " . $e->getMessage());
            throw new \Exception("Could not connect to Gemini API: " . $e->getMessage());
        }
    }

    /**
     * Get the MIME type for an audio file.
     */
    protected function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'webm' => 'audio/webm',
            default => 'audio/mpeg',
        };
    }

    /**
     * Simulate transcription for development/testing when no API key is available.
     */
    protected function simulateTranscription(string $filePath): array
    {
        $fileName = basename($filePath);
        $transcript = implode("\n", [
            "[00:00] Agente: Buenos días, gracias por comunicarse con nosotros. Mi nombre es María, ¿en qué puedo ayudarle?",
            "[00:04] Cliente: Hola María, buenos días. Llamo porque tengo un problema con mi factura del mes pasado.",
            "[00:10] Agente: Entiendo, lamento mucho el inconveniente. Permítame verificar su cuenta. ¿Me puede proporcionar su número de identificación o el número de cuenta?",
            "[00:18] Cliente: Sí, claro. Mi número de cuenta es 4523-8891.",
            "[00:22] Agente: Perfecto, ya tengo su cuenta en pantalla. Veo que efectivamente hay un cargo adicional de \$15.000 que parece ser un cobro duplicado. ¿Es ese el problema que menciona?",
            "[00:32] Cliente: Exactamente, ese cargo no debería estar ahí.",
            "[00:35] Agente: Tiene toda la razón, señor. Voy a proceder a generar una nota de crédito por ese monto. El ajuste se verá reflejado en su próxima factura. ¿Hay algo más en que pueda ayudarle?",
            "[00:45] Cliente: No, eso era todo. Muchas gracias por la rápida solución.",
            "[00:49] Agente: Con mucho gusto. Que tenga un excelente día.",
            "[00:52] Cliente: Igualmente, hasta luego.",
        ]);

        Log::info("AudioTranscriptionService: Simulated transcription for {$fileName}");

        return [
            'transcript' => $transcript,
            'sentiment' => [
                'overall' => 'positivo',
                'overall_score' => 0.7,
                'agent' => [
                    'sentiment' => 'positivo',
                    'score' => 0.8,
                    'tone' => 'Profesional, empático y resolutivo',
                ],
                'client' => [
                    'sentiment' => 'positivo',
                    'score' => 0.6,
                    'tone' => 'Inicialmente preocupado, luego satisfecho con la resolución',
                    'satisfaction' => 'satisfecho',
                ],
                'summary' => 'Llamada positiva donde el agente resolvió de manera eficiente un problema de cobro duplicado. El cliente pasó de preocupado a satisfecho.',
            ],
        ];
    }
}
