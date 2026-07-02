<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GoogleTextToSpeechStudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class TextToSpeechStudioController extends Controller
{
    public function index()
    {
        return view('settings.tts', [
            'settings' => $this->settings(),
            'formats' => $this->formatOptions(),
            'languages' => $this->languageOptions(),
            'voices' => $this->voiceOptions(),
        ]);
    }

    public function store(Request $request, GoogleTextToSpeechStudioService $tts)
    {
        $validated = $request->validate($this->rules());
        $this->saveSettings($validated);

        $settings = $this->settings();

        try {
            $audio = $tts->synthesize($settings, (string) $validated['tts_text']);
        } catch (RuntimeException $exception) {
            Log::warning('TTS studio generation failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['tts_text' => 'No se pudo generar el audio con la configuración interna actual.']);
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

        $languages = $this->languageOptions();
        $settings['auth_mode'] = 'gemini_api_key';
        $settings['endpoint'] = $defaults['gemini_endpoint'];
        $settings['model_name'] = $defaults['model_name'];
        $settings['language_code'] = array_key_exists((string) $settings['language_code'], $languages)
            ? (string) $settings['language_code']
            : $defaults['language_code'];
        $settings['language_label'] = $languages[$settings['language_code']] ?? $defaults['language_label'];
        $settings['audio_encoding'] = 'LINEAR16';
        $settings['audio_disk'] = $settings['audio_disk'] ?: config('filesystems.default', 'local');

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'intent' => ['nullable', 'in:generate'],
            'language_code' => ['required', 'string', Rule::in(array_keys($this->languageOptions()))],
            'voice_name' => ['required', 'string', Rule::in(array_keys($this->voiceOptions()))],
            'audio_encoding' => ['required', 'string', Rule::in(array_keys($this->formatOptions()))],
            'speaking_rate' => ['required', 'numeric', 'min:0.25', 'max:2'],
            'pitch' => ['required', 'numeric', 'min:-20', 'max:20'],
            'style_instructions' => ['nullable', 'string', 'max:4000'],
            'tts_text' => ['required', 'string', 'max:5000'],
        ];
    }

    private function saveSettings(array $validated): void
    {
        $defaults = GoogleTextToSpeechStudioService::DEFAULTS;
        $language = (string) $validated['language_code'];

        Setting::set('tts_studio.auth_mode', 'gemini_api_key', 'string', 'tts_studio');
        Setting::set('tts_studio.endpoint', $defaults['gemini_endpoint'], 'string', 'tts_studio');
        Setting::set('tts_studio.model_name', $defaults['model_name'], 'string', 'tts_studio');
        Setting::set('tts_studio.language_code', $language, 'string', 'tts_studio');
        Setting::set('tts_studio.language_label', $this->languageOptions()[$language], 'string', 'tts_studio');
        Setting::set('tts_studio.voice_name', $validated['voice_name'], 'string', 'tts_studio');
        Setting::set('tts_studio.audio_encoding', 'LINEAR16', 'string', 'tts_studio');
        Setting::set('tts_studio.style_instructions', $validated['style_instructions'] ?? '', 'string', 'tts_studio');
        Setting::set('tts_studio.speaking_rate', (float) $validated['speaking_rate'], 'float', 'tts_studio');
        Setting::set('tts_studio.pitch', (float) $validated['pitch'], 'float', 'tts_studio');
    }

    private function downloadFileName(array $settings, string $extension): string
    {
        $voice = Str::slug((string) $settings['voice_name']) ?: 'tts';
        $language = Str::slug((string) $settings['language_code']) ?: 'audio';

        return "tts-{$voice}-{$language}-".now()->format('Ymd-His').".{$extension}";
    }

    /**
     * @return array<string, string>
     */
    private function formatOptions(): array
    {
        return [
            'LINEAR16' => 'WAV',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function languageOptions(): array
    {
        return [
            'es-419' => 'Español (Latinoamérica)',
            'es-PE' => 'Español (Perú)',
            'es-MX' => 'Español (México)',
            'es-CO' => 'Español (Colombia)',
            'es-CL' => 'Español (Chile)',
            'es-AR' => 'Español (Argentina)',
            'es-ES' => 'Español (España)',
            'es' => 'Español neutro',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function voiceOptions(): array
    {
        return [
            'Zephyr' => 'Zephyr - Brillante',
            'Puck' => 'Puck - Dinámica',
            'Charon' => 'Charon - Informativa',
            'Kore' => 'Kore - Firme',
            'Fenrir' => 'Fenrir - Enérgica',
            'Leda' => 'Leda - Juvenil',
            'Orus' => 'Orus - Firme',
            'Aoede' => 'Aoede - Ligera',
            'Callirrhoe' => 'Callirrhoe - Relajada',
            'Autonoe' => 'Autonoe - Brillante',
            'Enceladus' => 'Enceladus - Susurrante',
            'Iapetus' => 'Iapetus - Clara',
            'Umbriel' => 'Umbriel - Relajada',
            'Algieba' => 'Algieba - Suave',
            'Despina' => 'Despina - Suave',
            'Erinome' => 'Erinome - Clara',
            'Algenib' => 'Algenib - Rasposa',
            'Rasalgethi' => 'Rasalgethi - Informativa',
            'Laomedeia' => 'Laomedeia - Dinámica',
            'Achernar' => 'Achernar - Suave',
            'Alnilam' => 'Alnilam - Firme',
            'Schedar' => 'Schedar - Uniforme',
            'Gacrux' => 'Gacrux - Madura',
            'Pulcherrima' => 'Pulcherrima - Directa',
            'Achird' => 'Achird - Amigable',
            'Zubenelgenubi' => 'Zubenelgenubi - Casual',
            'Vindemiatrix' => 'Vindemiatrix - Gentil',
            'Sadachbia' => 'Sadachbia - Vivaz',
            'Sadaltager' => 'Sadaltager - Conocedora',
            'Sulafat' => 'Sulafat - Cálida',
        ];
    }
}
