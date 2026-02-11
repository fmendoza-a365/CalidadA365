<x-app-layout>
    <x-slot name="header">Configuración de IA</x-slot>

    <div class="space-y-6">


        <form method="POST" action="{{ route('settings.ai.update') }}" x-data="{ provider: '{{ $currentProvider }}' }">
            @csrf

            <!-- Selección de Proveedor -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Proveedor de IA</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selecciona qué servicio de IA usar para evaluar las transcripciones</p>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($providers as $key => $provider)
                            <label class="relative cursor-pointer" @click="provider = '{{ $key }}'">
                                <input type="radio" name="provider" value="{{ $key }}" 
                                    class="sr-only peer" 
                                    x-bind:checked="provider === '{{ $key }}'"
                                    {{ $currentProvider === $key ? 'checked' : '' }}>
                                <div class="p-4 rounded-xl border-2 transition-all
                                    peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-500/10
                                    border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600">
                                    <div class="flex items-center gap-3 mb-2">
                                        @if($key === 'simulated')
                                            <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                            </div>
                                        @elseif($key === 'openai')
                                            <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                                </svg>
                                            </div>
                                        @elseif($key === 'gemini')
                                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </div>
                                        @elseif($key === 'claude')
                                            <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $provider['name'] }}</div>
                                            @if($provider['configured'] && $key !== 'simulated')
                                                <span class="text-xs text-emerald-600 dark:text-emerald-400">✓ Configurado</span>
                                            @elseif($key !== 'simulated')
                                                <span class="text-xs text-amber-600 dark:text-amber-400">Sin API Key</span>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $provider['description'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Configuración OpenAI -->
            <div class="card mb-6" x-show="provider === 'openai'" x-transition>
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">OpenAI</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configura tu API Key de OpenAI</p>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="form-group">
                        <label for="openai_api_key" class="form-label">API Key</label>
                        <input type="password" name="openai_api_key" id="openai_api_key" 
                            value="{{ $settings['openai_api_key'] }}"
                            class="form-input" placeholder="sk-proj-xxxxxxxxxxxxxxxx">
                        <p class="text-xs text-gray-500 mt-1">
                            Obtén tu API Key en <a href="https://platform.openai.com/api-keys" target="_blank" class="text-indigo-600 hover:underline">platform.openai.com</a>
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="openai_model" class="form-label">Modelo (Nombre Técnico)</label>
                            <input type="text" name="openai_model" id="openai_model" 
                                value="{{ $settings['openai_model'] }}" 
                                list="openai_models"
                                class="form-input" placeholder="Ej: gpt-4o">
                            <datalist id="openai_models">
                                <option value="gpt-4o-mini">GPT-4o Mini (Económico)</option>
                                <option value="gpt-4o">GPT-4o (Recomendado)</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                            </datalist>
                            <p class="text-xs text-gray-500 mt-1">Escribe el nombre del modelo o selecciona uno.</p>
                        </div>
                        <div class="form-group">
                            <label for="openai_max_tokens" class="form-label">Max Tokens</label>
                            <input type="number" name="openai_max_tokens" id="openai_max_tokens" 
                                value="{{ $settings['openai_max_tokens'] }}"
                                class="form-input" min="1" max="128000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="openai_temperature" class="form-label flex justify-between">
                            <span>Temperatura (Creatividad)</span>
                            <span x-text="openai_temp" class="text-gray-500"></span>
                        </label>
                        <div class="flex items-center gap-4" x-data="{ openai_temp: {{ $settings['openai_temperature'] }} }">
                            <input type="range" name="openai_temperature" id="openai_temperature" 
                                x-model="openai_temp"
                                min="0" max="2" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <input type="number" x-model="openai_temp" class="form-input w-20 text-center" readonly>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">0 = Preciso/Determinista, 1+ = Creativo/Variado</p>
                    </div>
                </div>
            </div>

            <!-- Configuración Gemini -->
            <div class="card mb-6" x-show="provider === 'gemini'" x-transition>
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Google Gemini</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configura tu API Key de Google AI</p>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="form-group">
                        <label for="gemini_api_key" class="form-label">API Key</label>
                        <input type="password" name="gemini_api_key" id="gemini_api_key" 
                            value="{{ $settings['gemini_api_key'] }}"
                            class="form-input" placeholder="AIzaSyBxxxxxxxxxxxxxxxx">
                        <p class="text-xs text-gray-500 mt-1">
                            Obtén tu API Key en <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-indigo-600 hover:underline">Google AI Studio</a>
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="gemini_model" class="form-label">Modelo (Nombre Técnico)</label>
                        <input type="text" name="gemini_model" id="gemini_model" 
                            value="{{ $settings['gemini_model'] }}" 
                            list="gemini_models"
                            class="form-input" placeholder="Ej: gemini-1.5-flash">
                        <datalist id="gemini_models">
                            <option value="gemini-2.0-flash">Gemini 2.0 Flash (Nuevo)</option>
                            <option value="gemini-1.5-flash">Gemini 1.5 Flash (Estándar)</option>
                            <option value="gemini-1.5-pro">Gemini 1.5 Pro (Preciso)</option>
                            <option value="gemini-flash-latest">Gemini Flash Latest</option>
                        </datalist>
                        <p class="text-xs text-gray-500 mt-1">Escribe el nombre de cualquier modelo soportado por Gemini API.</p>
                    </div>

                    <div class="form-group">
                        <label for="gemini_temperature" class="form-label flex justify-between">
                            <span>Temperatura (Creatividad)</span>
                            <span x-text="gemini_temp" class="text-gray-500"></span>
                        </label>
                        <div class="flex items-center gap-4" x-data="{ gemini_temp: {{ $settings['gemini_temperature'] }} }">
                            <input type="range" name="gemini_temperature" id="gemini_temperature" 
                                x-model="gemini_temp"
                                min="0" max="1" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <input type="number" x-model="gemini_temp" class="form-input w-20 text-center" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración Claude -->
            <div class="card mb-6" x-show="provider === 'claude'" x-transition>
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Anthropic Claude</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configura tu API Key de Anthropic</p>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="form-group">
                        <label for="claude_api_key" class="form-label">API Key</label>
                        <input type="password" name="claude_api_key" id="claude_api_key" 
                            value="{{ $settings['claude_api_key'] }}"
                            class="form-input" placeholder="sk-ant-xxxxxxxxxxxxxxxx">
                        <p class="text-xs text-gray-500 mt-1">
                            Obtén tu API Key en <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-indigo-600 hover:underline">console.anthropic.com</a>
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="claude_model" class="form-label">Modelo (Nombre Técnico)</label>
                            <input type="text" name="claude_model" id="claude_model" 
                                value="{{ $settings['claude_model'] }}" 
                                list="claude_models"
                                class="form-input" placeholder="Ej: claude-3-haiku-20240307">
                            <datalist id="claude_models">
                                <option value="claude-3-haiku-20240307">Claude 3 Haiku</option>
                                <option value="claude-3-sonnet-20240229">Claude 3 Sonnet</option>
                                <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label for="claude_max_tokens" class="form-label">Max Tokens</label>
                            <input type="number" name="claude_max_tokens" id="claude_max_tokens" 
                                value="{{ $settings['claude_max_tokens'] }}"
                                class="form-input" min="1" max="4096">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="claude_temperature" class="form-label flex justify-between">
                            <span>Temperatura (Creatividad)</span>
                            <span x-text="claude_temp" class="text-gray-500"></span>
                        </label>
                        <div class="flex items-center gap-4" x-data="{ claude_temp: {{ $settings['claude_temperature'] }} }">
                            <input type="range" name="claude_temperature" id="claude_temperature" 
                                x-model="claude_temp"
                                min="0" max="1" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                            <input type="number" x-model="claude_temp" class="form-input w-20 text-center" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración Simulado -->
            <div class="card mb-6" x-show="provider === 'simulated'" x-transition>
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Modo Simulado</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ideal para desarrollo y pruebas</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-4">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>El modo simulado genera evaluaciones aleatorias. No usar en producción.</span>
                    </div>
                    <div class="form-group">
                        <label for="simulated_compliance_rate" class="form-label">Tasa de Cumplimiento Simulada (%)</label>
                        <input type="number" name="simulated_compliance_rate" id="simulated_compliance_rate" 
                            value="{{ $settings['simulated_compliance_rate'] }}"
                            class="form-input w-32" min="0" max="100">
                        <p class="text-xs text-gray-500 mt-1">Porcentaje de criterios que se marcarán como "cumple"</p>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="btn-secondary btn-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver
                </a>
                <button type="submit" class="btn-primary btn-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
