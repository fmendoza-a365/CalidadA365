@php
    $interaction = $interaction ?? null;
    $uploadMetadata = $interaction?->metadata['upload'] ?? [];
    $analysisOptions = $uploadMetadata['analysis_options'] ?? [];
    $tags = old('tags', implode(', ', $uploadMetadata['tags'] ?? []));
    $emotionChecked = filter_var(old('analyze_emotion', $analysisOptions['emotion'] ?? true), FILTER_VALIDATE_BOOLEAN);
    $criticalChecked = filter_var(old('detect_critical_compliance', $analysisOptions['critical_compliance'] ?? true), FILTER_VALIDATE_BOOLEAN);
    $advancedOpen = $advancedOpen ?? false;
@endphp

<details class="card overflow-hidden" {{ $advancedOpen ? 'open' : '' }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 marker:hidden">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Clasificación de la Interacción</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Datos opcionales para segmentar y orientar la IA.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-neutral">Opcional</span>
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
            </svg>
        </div>
    </summary>

    <div class="border-t border-gray-100 px-6 py-5 dark:border-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="form-group">
                <label for="external_id" class="form-label">ID externo</label>
                <input type="text" name="external_id" id="external_id"
                    value="{{ old('external_id', $interaction?->external_id) }}" class="form-input font-mono"
                    maxlength="120" placeholder="CRM-12345">
                <x-input-error :messages="$errors->get('external_id')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="channel" class="form-label">Canal</label>
                <select name="channel" id="channel" class="form-select">
                    @foreach($formOptions['channels'] as $value => $label)
                        <option value="{{ $value }}" {{ old('channel', $interaction?->channel ?? 'call') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('channel')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="direction" class="form-label">Tipo</label>
                <select name="direction" id="direction" class="form-select">
                    <option value="">No especificado</option>
                    @foreach($formOptions['directions'] as $value => $label)
                        <option value="{{ $value }}" {{ old('direction', $interaction?->direction) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('direction')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="priority" class="form-label">Prioridad</label>
                <select name="priority" id="priority" class="form-select">
                    @foreach($formOptions['priorities'] as $value => $label)
                        <option value="{{ $value }}" {{ old('priority', $interaction?->priority ?? 'normal') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('priority')" class="mt-1" />
            </div>

            <div class="form-group md:col-span-2">
                <label for="contact_reason" class="form-label">Motivo de contacto</label>
                <input type="text" name="contact_reason" id="contact_reason"
                    value="{{ old('contact_reason', $interaction?->contact_reason) }}" class="form-input"
                    maxlength="160" placeholder="Reclamo por activación pendiente">
                <x-input-error :messages="$errors->get('contact_reason')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="outcome" class="form-label">Resultado</label>
                <select name="outcome" id="outcome" class="form-select">
                    <option value="">No especificado</option>
                    @foreach($formOptions['outcomes'] as $value => $label)
                        <option value="{{ $value }}" {{ old('outcome', $interaction?->outcome) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('outcome')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="customer_reference" class="form-label">Cliente ref.</label>
                <input type="text" name="customer_reference" id="customer_reference"
                    value="{{ old('customer_reference', $interaction?->customer_reference) }}" class="form-input"
                    maxlength="120" placeholder="***1234">
                <x-input-error :messages="$errors->get('customer_reference')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="queue_name" class="form-label">Skill / Cola</label>
                <input type="text" name="queue_name" id="queue_name"
                    value="{{ old('queue_name', $interaction?->queue_name) }}" class="form-input"
                    maxlength="120" placeholder="Soporte premium">
                <x-input-error :messages="$errors->get('queue_name')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="product_name" class="form-label">Producto</label>
                <input type="text" name="product_name" id="product_name"
                    value="{{ old('product_name', $interaction?->product_name) }}" class="form-input"
                    maxlength="120" placeholder="Cuenta digital">
                <x-input-error :messages="$errors->get('product_name')" class="mt-1" />
            </div>

            <div class="form-group md:col-span-2">
                <label for="tags" class="form-label">Etiquetas</label>
                <input type="text" name="tags" id="tags" value="{{ $tags }}" class="form-input"
                    maxlength="500" placeholder="reclamo, retención, riesgo">
                <x-input-error :messages="$errors->get('tags')" class="mt-1" />
            </div>
        </div>

        <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Evaluación IA</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Configuración avanzada del análisis.</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">La duración se calcula desde el audio o timestamps.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[180px_220px_1fr_1fr]">
                <div class="form-group">
                    <label for="language" class="form-label">Idioma</label>
                    <select name="language" id="language" class="form-select">
                        @foreach($formOptions['languages'] as $value => $label)
                            <option value="{{ $value }}" {{ old('language', $uploadMetadata['language'] ?? 'es') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('language')" class="mt-1" />
                </div>

                <div class="form-group">
                    <label for="diarization_mode" class="form-label">Diarización</label>
                    <select name="diarization_mode" id="diarization_mode" class="form-select">
                        @foreach($formOptions['diarizationModes'] as $value => $label)
                            <option value="{{ $value }}" {{ old('diarization_mode', $uploadMetadata['diarization_mode'] ?? 'auto') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('diarization_mode')" class="mt-1" />
                </div>

                <label class="flex min-h-[46px] items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                    <input type="hidden" name="analyze_emotion" value="0">
                    <input type="checkbox" name="analyze_emotion" value="1" class="form-checkbox" {{ $emotionChecked ? 'checked' : '' }}>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Análisis emocional</span>
                </label>

                <label class="flex min-h-[46px] items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                    <input type="hidden" name="detect_critical_compliance" value="0">
                    <input type="checkbox" name="detect_critical_compliance" value="1" class="form-checkbox" {{ $criticalChecked ? 'checked' : '' }}>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Críticos de cumplimiento</span>
                </label>
            </div>

            <div class="form-group mt-4">
                <label for="ai_context" class="form-label">Contexto adicional para IA</label>
                <textarea name="ai_context" id="ai_context" rows="3" class="form-textarea"
                    placeholder="Ej. Cliente llamó por una promesa incumplida; revisar empatía y cierre.">{{ old('ai_context', $uploadMetadata['ai_context'] ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('ai_context')" class="mt-1" />
            </div>
        </div>
    </div>
</details>
