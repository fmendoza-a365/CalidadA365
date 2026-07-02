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
                speed: Number('{{ old('speaking_rate', $settings['speaking_rate']) }}'),
                pitch: Number('{{ old('pitch', $settings['pitch']) }}')
            }">
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
                        <textarea name="tts_text" id="tts_text" rows="9" class="form-textarea"
                            placeholder="Escribe aquí el texto que quieres convertir en audio.">{{ old('tts_text') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary btn-lg">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M6.5 5.2v9.6c0 .7.8 1.1 1.4.7l7.4-4.8a.8.8 0 000-1.4L7.9 4.5c-.6-.4-1.4 0-1.4.7z" />
                    </svg>
                    Generar audio
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
