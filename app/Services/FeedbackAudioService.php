<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Support\AiProviderErrors;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FeedbackAudioService
{
    public function isEnabled(): bool
    {
        return (bool) config('ai.feedback_tts.enabled', false);
    }

    public function generate(Evaluation $evaluation): array
    {
        $text = $this->narratedFeedbackText($evaluation);

        if ($text === '') {
            throw new RuntimeException('La evaluación no tiene feedback para narrar.');
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(90)
            ->post((string) config('ai.feedback_tts.endpoint'), $this->synthesizePayload($text));

        if ($response->failed()) {
            throw new RuntimeException('Google Cloud TTS error: '.AiProviderErrors::sanitize($response->body()));
        }

        $audioContent = $response->json('audioContent');
        if (! is_string($audioContent) || $audioContent === '') {
            throw new RuntimeException('Google Cloud TTS no devolvió audioContent.');
        }

        $audioBinary = base64_decode($audioContent, true);
        if ($audioBinary === false) {
            throw new RuntimeException('Google Cloud TTS devolvió audioContent inválido.');
        }

        $disk = (string) config('ai.feedback_tts.audio_disk', config('filesystems.default'));
        $path = "feedbacks/evaluations/{$evaluation->id}.mp3";

        Storage::disk($disk)->put($path, $audioBinary);

        return [
            'disk' => $disk,
            'path' => $path,
            'bytes' => strlen($audioBinary),
        ];
    }

    public function narratedFeedbackText(Evaluation $evaluation): string
    {
        return collect($evaluation->structuredAiFeedback())
            ->map(fn (array $section): string => trim(($section['title'] ?? '').': '.($section['content'] ?? '')))
            ->filter(fn (string $line): bool => $line !== '' && ! str_contains($line, 'Sin contenido específico'))
            ->implode("\n");
    }

    private function accessToken(): string
    {
        $configuredToken = config('ai.feedback_tts.access_token');
        if (is_string($configuredToken) && trim($configuredToken) !== '') {
            return trim($configuredToken);
        }

        $credentialsPath = config('ai.feedback_tts.credentials_path');
        if (is_string($credentialsPath) && trim($credentialsPath) !== '') {
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.trim($credentialsPath));
        }

        $credentials = ApplicationDefaultCredentials::getCredentials((string) config('ai.feedback_tts.scope'));
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('No se pudo obtener access token de Google Cloud para TTS.');
        }

        return $accessToken;
    }

    private function synthesizePayload(string $text): array
    {
        $model = trim((string) config('ai.feedback_tts.model', ''));
        $voice = [
            'languageCode' => (string) config('ai.feedback_tts.language', 'es-419'),
            'name' => (string) config('ai.feedback_tts.voice', 'Orus'),
        ];
        $input = [
            'text' => $this->limitBytes($text, (int) config('ai.feedback_tts.text_byte_limit', 900)),
        ];

        if ($model !== '') {
            $voice['modelName'] = $model;
            $input = [
                'prompt' => $this->limitBytes((string) config('ai.feedback_tts.prompt'), (int) config('ai.feedback_tts.prompt_byte_limit', 900)),
                'text' => $input['text'],
            ];
        }

        return [
            'input' => $input,
            'voice' => $voice,
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => 1.05,
                'pitch' => 0,
            ],
        ];
    }

    private function limitBytes(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($limit <= 0 || strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_strcut($text, 0, $limit, 'UTF-8'));
    }
}
