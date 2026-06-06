<x-app-layout>
    <x-slot name="header">Transcripción #{{ $interaction->id }}</x-slot>

    @php
        $audioReviewConfig = null;
        $interactionCampaign = $interaction->campaign;

        if ($interaction->isAudio()) {
            $audioReviewConfig = json_encode([
                'audioUrl' => route('transcripts.audio', $interaction),
                'timeline' => $audioTimeline ?? [],
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
        }
    @endphp

    <div class="space-y-6"
        @if($interaction->isAudio())
            data-audio-review='{{ $audioReviewConfig }}'
            x-data="audioReview(JSON.parse($el.dataset.audioReview))"
        @endif>
        <!-- Información General -->
        <div class="card">
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6 md:grid-cols-6">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Campaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interactionCampaign?->parent?->name ?? $interactionCampaign?->name ?? 'Campaña no disponible' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Subcampaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interactionCampaign?->parent ? $interactionCampaign->name : 'General' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Asesor</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->agent?->full_name ?? 'Usuario eliminado' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">SN / Código</div>
                        <p class="font-mono text-sm font-medium text-gray-900 dark:text-white">
                            {{ $interaction->call_sn ?: '—' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Supervisor</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->supervisor?->full_name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Fecha de Llamada</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @php
            $uploadMetadata = $interaction->metadata['upload'] ?? [];
            $tags = $uploadMetadata['tags'] ?? [];
            $analysisOptions = $uploadMetadata['analysis_options'] ?? [];
        @endphp

        <div class="card">
            <div class="card-header flex items-center justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Contexto Operativo</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Metadatos cargados para priorización, revisión e IA.</p>
                </div>
                @if($interaction->priority)
                    @php
                        $priorityClass = match ($interaction->priority) {
                            'critical', 'risk' => 'badge-danger',
                            'high', 'complaint' => 'badge-warning',
                            default => 'badge-neutral',
                        };
                    @endphp
                    <span class="badge {{ $priorityClass }}">
                        {{ $formOptions['priorities'][$interaction->priority] ?? ucfirst($interaction->priority) }}
                    </span>
                @endif
            </div>
            <div class="card-body space-y-5">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Canal</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $formOptions['channels'][$interaction->channel] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $formOptions['directions'][$interaction->direction] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">ID externo</div>
                        <div class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $interaction->external_id ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Cargado por</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $interaction->uploadedBy?->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Motivo</div>
                        <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">{{ $interaction->contact_reason ?: '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Resultado</div>
                        <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">{{ $formOptions['outcomes'][$interaction->outcome] ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Cliente / Producto</div>
                        <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                            {{ $interaction->customer_reference ?: '—' }}
                            @if($interaction->product_name)
                                <span class="text-gray-400">/</span> {{ $interaction->product_name }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Etiquetas</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($tags as $tag)
                                <span class="badge badge-neutral">{{ $tag }}</span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Configuración IA</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="badge badge-info">{{ $formOptions['languages'][$uploadMetadata['language'] ?? 'es'] ?? 'Español' }}</span>
                            <span class="badge badge-neutral">{{ $formOptions['diarizationModes'][$uploadMetadata['diarization_mode'] ?? 'auto'] ?? 'Automática' }}</span>
                            @if($analysisOptions['emotion'] ?? false)
                                <span class="badge badge-success">Emociones</span>
                            @endif
                            @if($analysisOptions['critical_compliance'] ?? false)
                                <span class="badge badge-warning">Críticos</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($uploadMetadata['ai_context']))
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/40">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Contexto IA</div>
                        <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">{{ $uploadMetadata['ai_context'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Audio Player (for audio sources) -->
        @if($interaction->isAudio())
            @include('transcripts.partials.audio-review', ['timeline' => $audioTimeline ?? []])
        @endif

        <!-- Transcription Status (for audio being transcribed) -->
        @if($interaction->isTranscribing())
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 animate-spin" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Transcribiendo audio...</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">El audio está siendo procesado por Google
                                Gemini. La transcripción aparecerá automáticamente cuando termine.</p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($interaction->isTranscriptionFailed())
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-rose-100 dark:bg-rose-500/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-rose-600 dark:text-rose-400">Error en la transcripción</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No se pudo transcribir el audio. Verifica
                                que el archivo no esté dañado y que la API key de Gemini esté configurada correctamente.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Contenido de la Transcripción -->
        @if(!$interaction->isTranscribing() && !empty($interaction->transcript_text))
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        Conversación
                    </h3>
                </div>
                <div class="card-body" x-data="{ showRawTranscript: false }">
                    @include('transcripts.partials.conversation', [
                        'turns' => $conversationTurns ?? [],
                        'isAudio' => $interaction->isAudio(),
                    ])

                    <div class="mt-4">
                        <button type="button" @click="showRawTranscript = !showRawTranscript"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                            <span x-text="showRawTranscript ? 'Ocultar texto plano' : 'Ver texto plano'"></span>
                        </button>

                        <div x-show="showRawTranscript" x-cloak class="mt-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <pre class="max-h-96 overflow-y-auto whitespace-pre-wrap break-words font-mono text-sm leading-relaxed text-gray-700 dark:text-gray-300" style="overflow-wrap: anywhere;">{{ $interaction->transcript_text }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Evaluación (si existe) -->
        @if($interaction->evaluation)
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Resultado de Evaluación</h3>
                    <a href="{{ route('evaluations.show', $interaction->evaluation) }}" class="btn-primary btn-sm">
                        Ver Evaluación Completa
                    </a>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="text-center">
                            @php
                                $score = $interaction->evaluation->percentage_score;
                                $scoreClass = match (true) {
                                    $score >= 90 => 'score-excellent',
                                    $score >= 80 => 'score-good',
                                    $score >= 70 => 'score-average',
                                    default => 'score-poor',
                                };
                            @endphp
                            <div class="text-4xl font-bold {{ $scoreClass }}">{{ number_format($score, 0) }}%</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Puntaje</div>
                        </div>
                        <div class="text-center">
                            <span class="badge badge-neutral">{{ \App\Models\Evaluation::statusLabel($interaction->evaluation->status) }}</span>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">Estado</div>
                        </div>
                        <div class="text-center">
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ $interaction->evaluation->ai_processed_at?->format('d/m/Y H:i') ?? 'Pendiente' }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Evaluado</div>
                        </div>
                    </div>

                    @role('admin|qa_manager')
                    <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4" x-data="{ showDebug: false }">
                        <button @click="showDebug = !showDebug"
                            class="text-xs text-gray-500 hover:text-indigo-600 flex items-center gap-1 mx-auto">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span
                                x-text="showDebug ? 'Ocultar Detalles Técnicos (Debug)' : 'Ver Detalles Técnicos (Debug)'"></span>
                        </button>

                        <div x-show="showDebug" class="mt-4 space-y-4" style="display: none;">
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Prompt Enviado
                                    a IA</h4>
                                <div class="bg-gray-800 rounded-lg p-3 overflow-x-auto">
                                    <pre
                                        class="text-xs text-green-400 font-mono whitespace-pre-wrap">{{ $interaction->evaluation->ai_prompt ?? 'No registrado' }}</pre>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Respuesta
                                    Cruda de IA</h4>
                                <div class="bg-gray-800 rounded-lg p-3 overflow-x-auto">
                                    <pre
                                        class="text-xs text-blue-400 font-mono whitespace-pre-wrap">{{ $interaction->evaluation->ai_raw_response ?? 'No registrado' }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endrole
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Pendiente de Evaluación</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Esta transcripción aún no ha sido
                                    evaluada por IA</p>
                            </div>
                        </div>
                        @if($interaction->campaign?->activeFormVersion)
                                <form method="POST" action="{{ route('transcripts.evaluate', $interaction) }}" x-data="{ submitting: false }" @submit="if(submitting) { $event.preventDefault(); } else { submitting = true; }">
                                    @csrf
                                    
                                    <!-- Full Screen Loading Overlay for AI Evaluation -->
                                    <template x-teleport="body">
                                        <div x-show="submitting" style="display: none;" 
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 backdrop-blur-none"
                                             x-transition:enter-end="opacity-100 backdrop-blur-sm"
                                             class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-gray-900/80 backdrop-blur-sm">
                                            
                                            <!-- Modern Floating AI Element -->
                                            <div class="relative w-32 h-32 mb-8">
                                                <!-- Outer Radar Ping -->
                                                <div class="absolute inset-0 rounded-full bg-purple-500 opacity-20 animate-[ping_2s_cubic-bezier(0,0,0.2,1)_infinite]"></div>
                                                
                                                <!-- Spinning Ring -->
                                                <div class="absolute inset-2 rounded-full border-4 border-gray-700 border-t-purple-500 border-r-purple-500 animate-[spin_1.5s_linear_infinite]"></div>
                                                
                                                <!-- Inner Icon Container -->
                                                <div class="absolute inset-4 rounded-full bg-gray-800 flex items-center justify-center shadow-[0_0_30px_rgba(168,85,247,0.5)] border border-gray-700">
                                                    <svg class="w-10 h-10 text-purple-400 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            
                                            <!-- Animated Text -->
                                            <div class="text-center">
                                                <h2 class="text-2xl sm:text-3xl font-bold font-heading text-white mb-3 tracking-wide">
                                                    La IA está evaluando<span x-data="{ dots: '' }" x-init="setInterval(() => { dots = dots.length >= 3 ? '' : dots + '.' }, 500)" x-text="dots" class="inline-block w-6 text-left"></span>
                                                </h2>
                                                <p class="text-indigo-200 text-sm sm:text-base animate-pulse">
                                                    Analizando el contexto, sentimiento y criterios de calidad...<br>
                                                    <span class="text-gray-400 text-xs mt-2 block">(Esto toma entre 10 y 20 segundos)</span>
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <button type="submit" class="btn-primary btn-md" :disabled="submitting" :class="{ 'opacity-70 cursor-not-allowed': submitting }">
                                    <svg x-show="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    <svg x-show="submitting" class="w-4 h-4 animate-spin hidden" :class="{ 'hidden': !submitting, 'block': submitting }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="submitting ? 'Evaluando...' : 'Evaluar con IA'"></span>
                                </button>
                            </form>
                        @else
                            <span class="text-sm text-rose-600 dark:text-rose-400">La campaña no tiene ficha de calidad
                                activa</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div>
            <a href="{{ route('transcripts.index') }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Listado
            </a>
        </div>
    </div>
</x-app-layout>
