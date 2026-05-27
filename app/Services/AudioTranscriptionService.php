<?php

namespace App\Services;

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

    public function __construct()
    {
        $config = AiSettings::transcriptionConfig();

        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?: AiSettings::DEFAULTS['gemini_model'];
    }

    /**
     * Transcribe an audio file and analyze sentiment using Google Gemini API.
     *
     * @param  string  $filePath  Path relative to the storage disk
     * @param  string  $language  Language hint (default: Spanish)
     * @return array{transcript: string, sentiment: array} Transcript text and sentiment analysis
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

            // Clean loops if any survived
            $rawText = $this->cleanHallucinatedLoops($rawText);

            // Clean markdown code fences if present
            $rawText = preg_replace('/^```(?:json)?\s*/m', '', $rawText);
            $rawText = preg_replace('/\s*```$/m', '', $rawText);
            $rawText = trim($rawText);

            // Robust extraction: if it doesn't parse as a whole, try to extract the first { ... } block
            $parsed = json_decode($rawText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $rawText, $matches)) {
                    $jsonCandidate = trim($matches[0]);
                    $parsed = json_decode($jsonCandidate, true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || ! isset($parsed['transcript'])) {
                // Fallback: treat the entire response as plain transcript text
                Log::warning('AudioTranscriptionService: Could not parse JSON, using raw text as transcript');
                $cleanTranscript = str_replace('\n', "\n", $rawText);

                return [
                    'transcript' => $cleanTranscript,
                    'sentiment' => null,
                    'duration_seconds' => $audioDurationSeconds ?? $this->durationSecondsFromTranscript($cleanTranscript),
                ];
            }

            // Clean literal \n if they survived in the transcript field
            $cleanTranscript = str_replace('\n', "\n", $parsed['transcript']);

            Log::info('AudioTranscriptionService: Transcription + sentiment completed. Length: '.strlen($cleanTranscript).' chars');

            return [
                'transcript' => $cleanTranscript,
                'sentiment' => $parsed['sentiment'] ?? null,
                'duration_seconds' => $audioDurationSeconds ?? $this->durationSecondsFromTranscript($cleanTranscript),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $safeMessage = $this->sanitizeProviderError($e->getMessage());

            Log::error('AudioTranscriptionService: Connection error', [
                'error' => $safeMessage,
            ]);

            throw new \Exception('Could not connect to Gemini API: '.$safeMessage);
        }
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
        $byteRate = null;
        $dataSize = null;
        $length = strlen($audioData);

        while ($offset + 8 <= $length) {
            $chunkId = substr($audioData, $offset, 4);
            $chunkSize = unpack('V', substr($audioData, $offset + 4, 4))[1] ?? 0;
            $chunkDataOffset = $offset + 8;

            if ($chunkId === 'fmt ' && $chunkSize >= 16 && $chunkDataOffset + 16 <= $length) {
                $fmt = unpack('vformat/vchannels/VsampleRate/VbyteRate', substr($audioData, $chunkDataOffset, 14));
                $byteRate = (int) ($fmt['byteRate'] ?? 0);
            }

            if ($chunkId === 'data') {
                $dataSize = (int) $chunkSize;
            }

            if ($byteRate && $dataSize) {
                break;
            }

            $offset = $chunkDataOffset + $chunkSize + ($chunkSize % 2);
        }

        if (! $byteRate || ! $dataSize) {
            return null;
        }

        return max(1, (int) round($dataSize / $byteRate));
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

        return [
            'transcript' => $transcript,
            'duration_seconds' => $this->durationSecondsFromStoredAudio($filePath) ?? $this->durationSecondsFromTranscript($transcript),
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

    private function durationSecondsFromStoredAudio(string $filePath): ?int
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($filePath)) {
            return null;
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

    private function sanitizeProviderError(string $message): string
    {
        $message = preg_replace('/([?&]key=)[^&\s"]+/i', '$1[redacted]', $message) ?? $message;

        return preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '[redacted-api-key]', $message) ?? $message;
    }
}
