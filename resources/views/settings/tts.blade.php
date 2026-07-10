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
                    <p>No se pudo generar el audio.</p>
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
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-md" onclick="document.getElementById('tts-generated-audio')?.play()">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.5 5.2v9.6c0 .7.8 1.1 1.4.7l7.4-4.8a.8.8 0 000-1.4L7.9 4.5c-.6-.4-1.4 0-1.4.7z" />
                            </svg>
                            Escuchar
                        </button>
                        <a href="{{ $generated['download_url'] }}" class="btn-primary btn-md">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                            </svg>
                            Descargar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <audio id="tts-generated-audio" controls preload="metadata" class="w-full">
                        <source src="{{ $generated['preview_url'] }}">
                    </audio>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.tts.store') }}" class="space-y-6"
            x-data="{
                speed: Number(@js(old('speaking_rate', $settings['speaking_rate']))),
                pitch: Number(@js(old('pitch', $settings['pitch']))),
                ttsText: @js(old('tts_text', '')),
                generating: false,
                elapsed: 0,
                timer: null,
                get estimatedSegments() {
                    return Math.max(1, Math.ceil(new Blob([this.ttsText || '']).size / 1200));
                },
                get estimatedLabel() {
                    const seconds = Math.max(20, this.estimatedSegments * 25);
                    return this.formatDuration(seconds);
                },
                get elapsedLabel() {
                    return this.formatDuration(this.elapsed);
                },
                formatDuration(seconds) {
                    const minutes = Math.floor(seconds / 60);
                    const remaining = seconds % 60;

                    if (minutes <= 0) {
                        return `${remaining}s`;
                    }

                    return `${minutes}m ${remaining.toString().padStart(2, '0')}s`;
                },
                startGenerating() {
                    if (this.generating) {
                        return;
                    }

                    this.generating = true;
                    this.elapsed = 0;

                    this.timer = window.setInterval(() => {
                        this.elapsed += 1;
                    }, 1000);
                }
            }"
            x-on:submit="startGenerating()">
            @csrf
            <input type="hidden" name="intent" value="generate">

            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Generador de audio</h3>
                </div>
                <div class="card-body space-y-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="form-group">
                            <label for="language_code" class="form-label">Idioma</label>
                            <select name="language_code" id="language_code" class="form-select">
                                @foreach($languages as $code => $label)
                                    <option value="{{ $code }}" @selected(old('language_code', $settings['language_code']) === $code)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="voice_name" class="form-label">Voz</label>
                            <select name="voice_name" id="voice_name" class="form-select">
                                @foreach($voices as $voice => $label)
                                    <option value="{{ $voice }}" @selected(old('voice_name', $settings['voice_name']) === $voice)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="audio_encoding" class="form-label">Formato</label>
                            <select name="audio_encoding" id="audio_encoding" class="form-select">
                                @foreach($formats as $format => $label)
                                    <option value="{{ $format }}" @selected(old('audio_encoding', $settings['audio_encoding']) === $format)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div class="form-group">
                            <div class="flex items-center justify-between gap-3">
                                <label for="speaking_rate" class="form-label">Velocidad</label>
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200" x-text="speed.toFixed(2)"></span>
                            </div>
                            <input type="range" min="0.25" max="2" step="0.05" name="speaking_rate" id="speaking_rate"
                                x-model.number="speed" class="mt-2 w-full accent-indigo-600">
                        </div>

                        <div class="form-group">
                            <div class="flex items-center justify-between gap-3">
                                <label for="pitch" class="form-label">Pitch</label>
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200" x-text="pitch.toFixed(1)"></span>
                            </div>
                            <input type="range" min="-20" max="20" step="0.5" name="pitch" id="pitch"
                                x-model.number="pitch" class="mt-2 w-full accent-indigo-600">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="style_instructions" class="form-label">Estilo</label>
                        <textarea name="style_instructions" id="style_instructions" rows="3" class="form-textarea"
                            placeholder="Ejemplo: voz animada, vendedora y cercana.">{{ old('style_instructions', $settings['style_instructions']) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="tts_text" class="form-label">Texto</label>
                        <textarea name="tts_text" id="tts_text" rows="9" class="form-textarea" x-model="ttsText"
                            placeholder="Escribe aquí el texto que quieres convertir en audio.">{{ old('tts_text') }}</textarea>
                        <div class="mt-2 flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                            <span x-text="`${(ttsText || '').length.toLocaleString()} / 5,000 caracteres`"></span>
                            <span x-show="estimatedSegments > 1" x-cloak>
                                <span x-text="`${estimatedSegments} segmentos estimados`"></span>
                                <span> · tiempo aproximado </span>
                                <span x-text="estimatedLabel"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="generating" x-transition class="alert alert-info" x-cloak>
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 animate-spin text-blue-600 dark:text-blue-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Generando audio...</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Tiempo transcurrido: <span x-text="elapsedLabel"></span>.
                            <span x-show="estimatedSegments > 1">El texto se procesará en <span x-text="estimatedSegments"></span> segmentos.</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary btn-lg" :disabled="generating" :class="{ 'cursor-not-allowed opacity-70': generating }">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M6.5 5.2v9.6c0 .7.8 1.1 1.4.7l7.4-4.8a.8.8 0 000-1.4L7.9 4.5c-.6-.4-1.4 0-1.4.7z" />
                    </svg>
                    <span x-show="! generating">Generar audio</span>
                    <span x-show="generating" x-cloak>Generando...</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
