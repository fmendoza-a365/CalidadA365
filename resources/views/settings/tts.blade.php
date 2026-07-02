<x-app-layout>
    <x-slot name="header">Texto a Voz</x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <div>
                    <p>No se pudo completar la operación.</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if(session('tts_generated'))
            @php($generated = session('tts_generated'))
            <div class="card">
                <div class="card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Audio generado</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $generated['file_name'] }} · {{ $generated['encoding'] }} · {{ number_format(($generated['bytes'] ?? 0) / 1024, 1) }} KB
                        </p>
                    </div>
                    <a href="{{ $generated['download_url'] }}" class="btn-primary btn-md">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                        Descargar
                    </a>
                </div>
                <div class="card-body">
                    <audio controls preload="metadata" class="w-full">
                        <source src="{{ $generated['preview_url'] }}">
                    </audio>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.tts.store') }}" class="space-y-6">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Configuración Google TTS</h3>
                </div>
                <div class="card-body space-y-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="form-group">
                            <label for="auth_mode" class="form-label">Modo de autenticación</label>
                            <select name="auth_mode" id="auth_mode" class="form-select">
                                @foreach($authModes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('auth_mode', $settings['auth_mode']) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Gemini API key usa la clave guardada en Configuración IA si no ingresas una clave propia.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="endpoint" class="form-label">Endpoint</label>
                            <input type="url" name="endpoint" id="endpoint"
                                value="{{ old('endpoint', $settings['endpoint']) }}" class="form-input">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="form-group">
                            <label for="model_name" class="form-label">Modelo</label>
                            <input type="text" name="model_name" id="model_name" list="tts_models"
                                value="{{ old('model_name', $settings['model_name']) }}" class="form-input">
                            <datalist id="tts_models">
                                @foreach($models as $model)
                                    <option value="{{ $model }}">{{ $model }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="form-group">
                            <label for="language_code" class="form-label">Idioma / locale</label>
                            <input type="text" name="language_code" id="language_code"
                                value="{{ old('language_code', $settings['language_code']) }}" class="form-input">
                        </div>

                        <div class="form-group">
                            <label for="voice_name" class="form-label">Voz</label>
                            <input type="text" name="voice_name" id="voice_name" list="tts_voices"
                                value="{{ old('voice_name', $settings['voice_name']) }}" class="form-input">
                            <datalist id="tts_voices">
                                @foreach($voices as $voice)
                                    <option value="{{ $voice }}">{{ $voice }}</option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                        <div class="form-group">
                            <label for="audio_encoding" class="form-label">Formato</label>
                            <select name="audio_encoding" id="audio_encoding" class="form-select">
                                @foreach($encodings as $encoding)
                                    <option value="{{ $encoding }}" @selected(old('audio_encoding', $settings['audio_encoding']) === $encoding)>
                                        {{ $encoding }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="speaking_rate" class="form-label">Velocidad</label>
                            <input type="number" step="0.05" min="0.25" max="2" name="speaking_rate" id="speaking_rate"
                                value="{{ old('speaking_rate', $settings['speaking_rate']) }}" class="form-input">
                        </div>

                        <div class="form-group">
                            <label for="pitch" class="form-label">Pitch</label>
                            <input type="number" step="0.5" min="-20" max="20" name="pitch" id="pitch"
                                value="{{ old('pitch', $settings['pitch']) }}" class="form-input">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="form-group">
                            <label for="api_key" class="form-label">API key Gemini</label>
                            @if($settings['api_key_configured'])
                                <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                    <span class="font-medium text-emerald-700 dark:text-emerald-300">API key TTS guardada</span>
                                    <span class="ml-2 font-mono text-xs text-emerald-700 dark:text-emerald-200">{{ $settings['masked_api_key'] }}</span>
                                </div>
                            @elseif($settings['gemini_ai_key_configured'])
                                <div class="mb-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm dark:border-blue-500/20 dark:bg-blue-500/10">
                                    <span class="font-medium text-blue-700 dark:text-blue-300">Usará la API key Gemini de Configuración IA</span>
                                    <span class="ml-2 font-mono text-xs text-blue-700 dark:text-blue-200">{{ $settings['masked_gemini_ai_key'] }}</span>
                                </div>
                            @endif
                            <input type="password" name="api_key" id="api_key" value="" class="form-input" autocomplete="off">
                            @if($settings['api_key_configured'])
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" name="clear_api_key" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    Quitar API key TTS guardada
                                </label>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="credentials_path" class="form-label">Credenciales ADC</label>
                            <input type="text" name="credentials_path" id="credentials_path"
                                value="{{ old('credentials_path', $settings['credentials_path']) }}" class="form-input"
                                placeholder="/ruta/privada/google-service-account.json">
                        </div>

                        <div class="form-group">
                            <label for="access_token" class="form-label">OAuth access token temporal</label>
                            @if($settings['access_token_configured'])
                                <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                    <span class="font-medium text-emerald-700 dark:text-emerald-300">OAuth token guardado</span>
                                    <span class="ml-2 font-mono text-xs text-emerald-700 dark:text-emerald-200">{{ $settings['masked_access_token'] }}</span>
                                </div>
                            @endif
                            <input type="password" name="access_token" id="access_token" value="" class="form-input" autocomplete="off">
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Solo para Google Cloud OAuth. No pegues aquí una API key.
                            </p>
                            @if($settings['access_token_configured'])
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" name="clear_access_token" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    Quitar token guardado
                                </label>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Generador</h3>
                </div>
                <div class="card-body space-y-5">
                    <div class="form-group">
                        <label for="style_instructions" class="form-label">Style instructions</label>
                        <textarea name="style_instructions" id="style_instructions" rows="3" class="form-textarea">{{ old('style_instructions', $settings['style_instructions']) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="tts_text" class="form-label">Text to speak</label>
                        <textarea name="tts_text" id="tts_text" rows="8" class="form-textarea">{{ old('tts_text') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('settings.ai') }}" class="btn-secondary btn-md">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver
                </a>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <button type="submit" name="intent" value="save" class="btn-secondary btn-md">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        Guardar configuración
                    </button>
                    <button type="submit" name="intent" value="generate" class="btn-primary btn-md">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.5 5.2v9.6c0 .7.8 1.1 1.4.7l7.4-4.8a.8.8 0 000-1.4L7.9 4.5c-.6-.4-1.4 0-1.4.7z" />
                        </svg>
                        Generar audio
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
