<?php

namespace App\Services;

use App\Support\AiProviderErrors;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTextToSpeechStudioService
{
    public const DEFAULTS = [
        'endpoint' => 'https://texttospeech.googleapis.com/v1beta1/text:synthesize',
        'model_name' => 'gemini-3.1-flash-tts-preview',
        'language_code' => 'es-419',
        'voice_name' => 'Charon',
        'audio_encoding' => 'LINEAR16',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'style_instructions' => '',
        'credentials_path' => '',
        'access_token' => '',
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'audio_disk' => null,
    ];

    /**
     * @return array{content: string, bytes: int, encoding: string, extension: string, mime: string, payload: array<string, mixed>}
     */
    public function synthesize(array $settings, string $text): array
    {
        $payload = $this->payload($settings, $text);

        $response = Http::withToken($this->accessToken($settings))
            ->timeout(90)
            ->post((string) $settings['endpoint'], $payload);

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

        $encoding = strtoupper((string) $settings['audio_encoding']);

        return [
            'content' => $audioBinary,
            'bytes' => strlen($audioBinary),
            'encoding' => $encoding,
            'extension' => $this->extensionForEncoding($encoding),
            'mime' => $this->mimeForEncoding($encoding),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $settings, string $text): array
    {
        $text = $this->normalizeInput($text, 5000);
        if ($text === '') {
            throw new RuntimeException('Ingresa texto para generar audio.');
        }

        $prompt = $this->normalizeInput((string) ($settings['style_instructions'] ?? ''), 4000);

        if ($prompt !== '') {
            $text = $prompt."\n\n".$text;
        }

        return [
            'audioConfig' => [
                'audioEncoding' => strtoupper((string) $settings['audio_encoding']),
                'pitch' => (float) $settings['pitch'],
                'speakingRate' => (float) $settings['speaking_rate'],
            ],
            'input' => [
                'text' => $text,
            ],
            'voice' => [
                'languageCode' => (string) $settings['language_code'],
                'modelName' => (string) $settings['model_name'],
                'name' => (string) $settings['voice_name'],
            ],
        ];
    }

    private function accessToken(array $settings): string
    {
        $configuredToken = trim((string) ($settings['access_token'] ?? ''));
        if ($configuredToken !== '') {
            return $configuredToken;
        }

        $credentialsPath = trim((string) ($settings['credentials_path'] ?? ''));
        if ($credentialsPath !== '') {
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$credentialsPath);
        }

        $credentials = ApplicationDefaultCredentials::getCredentials((string) $settings['scope']);
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('No se pudo obtener access token de Google Cloud para TTS.');
        }

        return $accessToken;
    }

    private function normalizeInput(string $text, int $maxBytes): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($maxBytes <= 0 || strlen($text) <= $maxBytes) {
            return $text;
        }

        return rtrim(mb_strcut($text, 0, $maxBytes, 'UTF-8'));
    }

    private function extensionForEncoding(string $encoding): string
    {
        return match ($encoding) {
            'LINEAR16', 'MULAW', 'ALAW' => 'wav',
            'OGG_OPUS' => 'ogg',
            'PCM' => 'pcm',
            default => 'mp3',
        };
    }

    private function mimeForEncoding(string $encoding): string
    {
        return match ($encoding) {
            'LINEAR16', 'MULAW', 'ALAW' => 'audio/wav',
            'OGG_OPUS' => 'audio/ogg',
            'PCM' => 'application/octet-stream',
            default => 'audio/mpeg',
        };
    }
}
