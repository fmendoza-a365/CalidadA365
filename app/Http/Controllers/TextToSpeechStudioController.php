<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GoogleTextToSpeechStudioService;
use App\Support\AiSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TextToSpeechStudioController extends Controller
{
    public function index()
    {
        return view('settings.tts', [
            'settings' => $this->settings(),
            'encodings' => ['LINEAR16', 'MP3', 'OGG_OPUS', 'PCM', 'MULAW', 'ALAW'],
            'voices' => [
                'Charon', 'Orus', 'Kore', 'Puck', 'Aoede', 'Callirrhoe', 'Fenrir', 'Leda', 'Zephyr',
            ],
            'models' => [
                'gemini-3.1-flash-tts-preview',
                'gemini-2.5-flash-tts',
                'gemini-2.5-pro-tts',
                'gemini-2.5-flash-lite-preview-tts',
            ],
            'authModes' => [
                'gemini_api_key' => 'Gemini API key',
                'google_cloud_adc' => 'Google Cloud ADC / service account',
                'google_cloud_access_token' => 'Google Cloud OAuth access token',
            ],
        ]);
    }

    public function store(Request $request, GoogleTextToSpeechStudioService $tts)
    {
        $validated = $request->validate($this->rules($request->input('intent') === 'generate'));
        $this->saveSettings($validated, $request);

        if (($validated['intent'] ?? 'save') !== 'generate') {
            return redirect()->route('settings.tts')
                ->with('success', 'Configuración TTS guardada correctamente.');
        }

        $settings = $this->settings();

        try {
            $audio = $tts->synthesize($settings, (string) $validated['tts_text']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->except(['access_token']))
                ->withErrors(['tts_text' => $exception->getMessage()]);
        }

        $disk = (string) ($settings['audio_disk'] ?: config('filesystems.default', 'local'));
        $path = 'tts-studio/generated/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.$audio['extension'];
        $fileName = $this->downloadFileName($settings, $audio['extension']);

        Storage::disk($disk)->put($path, $audio['content']);

        $token = Crypt::encryptString(json_encode([
            'disk' => $disk,
            'path' => $path,
            'file_name' => $fileName,
            'mime' => $audio['mime'],
        ], JSON_THROW_ON_ERROR));

        return redirect()->route('settings.tts')->with([
            'success' => 'Audio generado correctamente.',
            'tts_generated' => [
                'file_name' => $fileName,
                'bytes' => $audio['bytes'],
                'encoding' => $audio['encoding'],
                'download_url' => route('settings.tts.download', ['token' => $token]),
                'preview_url' => route('settings.tts.download', ['token' => $token, 'inline' => 1]),
            ],
        ]);
    }

    public function download(Request $request)
    {
        $token = $request->validate([
            'token' => ['required', 'string'],
        ])['token'];

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(404);
        }

        $path = (string) ($payload['path'] ?? '');
        if (! str_starts_with($path, 'tts-studio/generated/')) {
            abort(404);
        }

        $diskName = (string) ($payload['disk'] ?? config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            abort(404);
        }

        $fileName = (string) ($payload['file_name'] ?? basename($path));
        $mime = (string) ($payload['mime'] ?? 'application/octet-stream');

        if ($request->boolean('inline')) {
            return response($disk->get($path), 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        return $disk->download($path, $fileName, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $defaults = GoogleTextToSpeechStudioService::DEFAULTS;
        $settings = [];

        foreach ($defaults as $key => $default) {
            $settings[$key] = Setting::get("tts_studio.{$key}", $default);
        }

        if (($settings['auth_mode'] ?? '') === 'gemini_api_key'
            && str_contains((string) $settings['endpoint'], 'texttospeech.googleapis.com')
        ) {
            $settings['endpoint'] = $defaults['gemini_endpoint'];
        }

        $settings['audio_disk'] = $settings['audio_disk'] ?: config('filesystems.default', 'local');
        $settings['access_token_configured'] = trim((string) $settings['access_token']) !== '';
        $settings['masked_access_token'] = $this->maskedSecret((string) $settings['access_token']);
        $settings['api_key_configured'] = trim((string) $settings['api_key']) !== '';
        $settings['masked_api_key'] = $this->maskedSecret((string) $settings['api_key']);
        $settings['gemini_ai_key_configured'] = AiSettings::apiKey('gemini') !== '';
        $settings['masked_gemini_ai_key'] = AiSettings::maskedApiKey('gemini');

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $generate): array
    {
        return [
            'intent' => ['required', 'in:save,generate'],
            'auth_mode' => ['required', 'in:gemini_api_key,google_cloud_adc,google_cloud_access_token'],
            'endpoint' => ['required', 'url', 'max:255'],
            'model_name' => ['required', 'string', 'max:120'],
            'language_code' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8}){0,2}$/'],
            'voice_name' => ['required', 'string', 'max:80'],
            'audio_encoding' => ['required', 'in:LINEAR16,MP3,OGG_OPUS,PCM,MULAW,ALAW'],
            'speaking_rate' => ['required', 'numeric', 'min:0.25', 'max:2'],
            'pitch' => ['required', 'numeric', 'min:-20', 'max:20'],
            'style_instructions' => ['nullable', 'string', 'max:4000'],
            'credentials_path' => ['nullable', 'string', 'max:500'],
            'access_token' => ['nullable', 'string', 'max:5000'],
            'api_key' => ['nullable', 'string', 'max:5000'],
            'clear_access_token' => ['nullable', 'boolean'],
            'clear_api_key' => ['nullable', 'boolean'],
            'tts_text' => [$generate ? 'required' : 'nullable', 'string', 'max:5000'],
        ];
    }

    private function saveSettings(array $validated, Request $request): void
    {
        foreach ([
            'auth_mode', 'endpoint', 'model_name', 'language_code', 'voice_name', 'audio_encoding',
            'style_instructions', 'credentials_path',
        ] as $key) {
            Setting::set("tts_studio.{$key}", $validated[$key] ?? '', 'string', 'tts_studio');
        }

        Setting::set('tts_studio.speaking_rate', (float) $validated['speaking_rate'], 'float', 'tts_studio');
        Setting::set('tts_studio.pitch', (float) $validated['pitch'], 'float', 'tts_studio');

        if ($request->boolean('clear_api_key')) {
            Setting::set('tts_studio.api_key', '', 'string', 'tts_studio');
        } elseif ($request->filled('api_key')) {
            Setting::set('tts_studio.api_key', (string) $validated['api_key'], 'string', 'tts_studio');
        } elseif (($validated['auth_mode'] ?? '') === 'gemini_api_key'
            && $request->filled('access_token')
            && $this->looksLikeGoogleApiKey((string) $validated['access_token'])
        ) {
            Setting::set('tts_studio.api_key', (string) $validated['access_token'], 'string', 'tts_studio');
            Setting::set('tts_studio.access_token', '', 'string', 'tts_studio');

            return;
        }

        if ($request->boolean('clear_access_token')) {
            Setting::set('tts_studio.access_token', '', 'string', 'tts_studio');
        } elseif ($request->filled('access_token')) {
            Setting::set('tts_studio.access_token', (string) $validated['access_token'], 'string', 'tts_studio');
        }
    }

    private function downloadFileName(array $settings, string $extension): string
    {
        $voice = Str::slug((string) $settings['voice_name']) ?: 'tts';
        $language = Str::slug((string) $settings['language_code']) ?: 'audio';

        return "tts-{$voice}-{$language}-".now()->format('Ymd-His').".{$extension}";
    }

    private function maskedSecret(string $secret): ?string
    {
        if ($secret === '') {
            return null;
        }

        $suffix = strlen($secret) > 4 ? substr($secret, -4) : '****';

        return "•••• •••• •••• {$suffix}";
    }

    private function looksLikeGoogleApiKey(string $value): bool
    {
        return str_starts_with(trim($value), 'AIza');
    }
}
