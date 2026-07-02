<?php

namespace App\Services;

use App\Support\AiProviderErrors;
use App\Support\AiSettings;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTextToSpeechStudioService
{
    public const DEFAULTS = [
        'endpoint' => 'https://texttospeech.googleapis.com/v1beta1/text:synthesize',
        'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/interactions',
        'auth_mode' => 'gemini_api_key',
        'model_name' => 'gemini-3.1-flash-tts-preview',
        'language_code' => 'es-419',
        'language_label' => 'Español (Latinoamérica)',
        'voice_name' => 'Charon',
        'audio_encoding' => 'LINEAR16',
        'speaking_rate' => 1.0,
        'pitch' => 0.0,
        'style_instructions' => '',
        'credentials_path' => '',
        'access_token' => '',
        'api_key' => '',
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'audio_disk' => null,
    ];

    /**
     * @return array{content: string, bytes: int, encoding: string, extension: string, mime: string, payload: array<string, mixed>}
     */
    public function synthesize(array $settings, string $text): array
    {
        if ($this->authMode($settings) === 'gemini_api_key') {
            return $this->synthesizeWithGeminiApi($settings, $text);
        }

        return $this->synthesizeWithCloudTts($settings, $text);
    }

    /**
     * @return array{content: string, bytes: int, encoding: string, extension: string, mime: string, payload: array<string, mixed>}
     */
    private function synthesizeWithCloudTts(array $settings, string $text): array
    {
        $payload = $this->cloudPayload($settings, $text);

        $response = Http::withToken($this->cloudAccessToken($settings))
            ->timeout(90)
            ->post($this->cloudEndpoint($settings), $payload);

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
     * @return array{content: string, bytes: int, encoding: string, extension: string, mime: string, payload: array<string, mixed>}
     */
    private function synthesizeWithGeminiApi(array $settings, string $text): array
    {
        $payload = $this->geminiPayload($settings, $text);

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->geminiApiKey($settings),
            'Content-Type' => 'application/json',
        ])
            ->timeout(90)
            ->post($this->geminiEndpoint($settings), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gemini TTS error: '.AiProviderErrors::sanitize($response->body()));
        }

        $audio = $this->geminiAudio($response->json() ?? []);
        if (($audio['data'] ?? '') === '') {
            throw new RuntimeException('Gemini TTS no devolvió audio en la respuesta.');
        }

        $audioBinary = base64_decode($audio['data'], true);
        if ($audioBinary === false) {
            throw new RuntimeException('Gemini TTS devolvió audio inválido.');
        }

        $audioBinary = $this->wavFromPcm($audioBinary, $audio['sample_rate'], $audio['channels']);

        return [
            'content' => $audioBinary,
            'bytes' => strlen($audioBinary),
            'encoding' => 'LINEAR16',
            'extension' => 'wav',
            'mime' => 'audio/wav',
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cloudPayload(array $settings, string $text): array
    {
        $text = $this->textWithStyle($settings, $text);

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

    /**
     * @return array<string, mixed>
     */
    private function geminiPayload(array $settings, string $text): array
    {
        return [
            'model' => (string) $settings['model_name'],
            'input' => $this->textWithStyle($settings, $text),
            'response_format' => [
                'type' => 'audio',
            ],
            'generation_config' => [
                'speech_config' => [
                    [
                        'voice' => (string) $settings['voice_name'],
                    ],
                ],
            ],
        ];
    }

    private function textWithStyle(array $settings, string $text): string
    {
        $text = $this->normalizeInput($text, 5000);
        if ($text === '') {
            throw new RuntimeException('Ingresa texto para generar audio.');
        }

        $prompt = $this->normalizeInput((string) ($settings['style_instructions'] ?? ''), 4000);
        $directives = $this->voiceDirectives($settings);

        if ($prompt !== '') {
            $directives[] = $prompt;
        }

        if ($directives !== []) {
            $text = implode(' ', $directives)."\n\n".$text;
        }

        return $text;
    }

    private function cloudAccessToken(array $settings): string
    {
        if ($this->authMode($settings) === 'google_cloud_access_token') {
            return $this->configuredOAuthToken($settings);
        }

        return $this->applicationDefaultAccessToken($settings);
    }

    private function configuredOAuthToken(array $settings): string
    {
        $configuredToken = trim((string) ($settings['access_token'] ?? ''));
        if ($configuredToken !== '') {
            if ($this->looksLikeGoogleApiKey($configuredToken)) {
                throw new RuntimeException('El valor ingresado parece una API key de Gemini. Para Cloud TTS usa un OAuth 2 access token o cambia el modo a Gemini API key.');
            }

            return $configuredToken;
        }

        throw new RuntimeException('Ingresa un OAuth 2 access token temporal o cambia el modo de autenticación.');
    }

    private function applicationDefaultAccessToken(array $settings): string
    {
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

    private function geminiApiKey(array $settings): string
    {
        $apiKey = trim((string) ($settings['api_key'] ?? ''));
        if ($apiKey !== '') {
            return $apiKey;
        }

        $legacyToken = trim((string) ($settings['access_token'] ?? ''));
        if ($this->looksLikeGoogleApiKey($legacyToken)) {
            return $legacyToken;
        }

        $apiKey = AiSettings::apiKey('gemini');
        if ($apiKey !== '') {
            return $apiKey;
        }

        throw new RuntimeException('Configura una API key de Gemini o guarda una API key de Gemini en Configuración IA.');
    }

    private function authMode(array $settings): string
    {
        $mode = (string) ($settings['auth_mode'] ?? self::DEFAULTS['auth_mode']);

        return in_array($mode, ['gemini_api_key', 'google_cloud_adc', 'google_cloud_access_token'], true)
            ? $mode
            : self::DEFAULTS['auth_mode'];
    }

    private function cloudEndpoint(array $settings): string
    {
        $endpoint = trim((string) ($settings['endpoint'] ?? ''));

        return $endpoint === '' || str_contains($endpoint, 'generativelanguage.googleapis.com')
            ? self::DEFAULTS['endpoint']
            : $endpoint;
    }

    private function geminiEndpoint(array $settings): string
    {
        $endpoint = trim((string) ($settings['endpoint'] ?? ''));

        return $endpoint === '' || str_contains($endpoint, 'texttospeech.googleapis.com')
            ? self::DEFAULTS['gemini_endpoint']
            : $endpoint;
    }

    /**
     * @return array{data: string, sample_rate: int, channels: int}
     */
    private function geminiAudio(array $response): array
    {
        foreach ([
            'output_audio.data',
            'outputAudio.data',
            'output_audio.inline_data.data',
            'outputAudio.inlineData.data',
        ] as $path) {
            $value = data_get($response, $path);

            if (is_string($value) && $value !== '') {
                return [
                    'data' => $value,
                    'sample_rate' => 24000,
                    'channels' => 1,
                ];
            }
        }

        foreach (($response['steps'] ?? []) as $step) {
            foreach (($step['content'] ?? []) as $content) {
                $value = $content['data'] ?? null;

                if (($content['type'] ?? null) === 'audio' && is_string($value) && $value !== '') {
                    return [
                        'data' => $value,
                        'sample_rate' => (int) ($content['sample_rate'] ?? 24000),
                        'channels' => (int) ($content['channels'] ?? 1),
                    ];
                }
            }
        }

        return [
            'data' => '',
            'sample_rate' => 24000,
            'channels' => 1,
        ];
    }

    private function wavFromPcm(string $pcm, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        if (str_starts_with($pcm, 'RIFF')) {
            return $pcm;
        }

        $bytesPerSample = intdiv($bitsPerSample, 8);
        $dataLength = strlen($pcm);
        $byteRate = $sampleRate * $channels * $bytesPerSample;
        $blockAlign = $channels * $bytesPerSample;

        return 'RIFF'
            .pack('V', 36 + $dataLength)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample)
            .'data'
            .pack('V', $dataLength)
            .$pcm;
    }

    private function looksLikeGoogleApiKey(string $value): bool
    {
        return str_starts_with(trim($value), 'AIza');
    }

    private function normalizeInput(string $text, int $maxBytes): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($maxBytes <= 0 || strlen($text) <= $maxBytes) {
            return $text;
        }

        return rtrim(mb_strcut($text, 0, $maxBytes, 'UTF-8'));
    }

    /**
     * @return array<int, string>
     */
    private function voiceDirectives(array $settings): array
    {
        $directives = [];
        $language = trim((string) ($settings['language_label'] ?? ''));

        if ($language !== '') {
            $directives[] = "Lee en {$language}.";
        }

        $speakingRate = (float) ($settings['speaking_rate'] ?? 1.0);
        if ($speakingRate <= 0.75) {
            $directives[] = 'Usa un ritmo pausado.';
        } elseif ($speakingRate < 0.95) {
            $directives[] = 'Usa un ritmo ligeramente pausado.';
        } elseif ($speakingRate > 1.25) {
            $directives[] = 'Usa un ritmo ágil.';
        } elseif ($speakingRate > 1.05) {
            $directives[] = 'Usa un ritmo ligeramente ágil.';
        } else {
            $directives[] = 'Usa un ritmo natural.';
        }

        $pitch = (float) ($settings['pitch'] ?? 0.0);
        if ($pitch <= -8) {
            $directives[] = 'Usa un tono más grave.';
        } elseif ($pitch < -2) {
            $directives[] = 'Usa un tono ligeramente grave.';
        } elseif ($pitch >= 8) {
            $directives[] = 'Usa un tono más agudo.';
        } elseif ($pitch > 2) {
            $directives[] = 'Usa un tono ligeramente agudo.';
        } else {
            $directives[] = 'Usa un tono natural.';
        }

        return $directives;
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
