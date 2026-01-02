<x-app-layout>
    <x-slot name="header">Transcripción #{{ $interaction->id }}</x-slot>

    <div class="space-y-6">
        <!-- Información General -->
        <div class="card">
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Campaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $interaction->campaign?->name ?? 'Campaña no disponible' }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Asesor</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $interaction->agent?->name ?? 'Usuario eliminado' }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Supervisor</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $interaction->supervisor?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Fecha de Llamada</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $interaction->occurred_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido de la Transcripción -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Contenido de la Transcripción</h3>
            </div>
            <div class="card-body">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 max-h-96 overflow-y-auto scrollbar-thin">
                    <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 font-mono leading-relaxed">{{ $interaction->transcript_text }}</pre>
                </div>
            </div>
        </div>

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
                                $scoreClass = match(true) {
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
                            @if($interaction->evaluation->status === 'visible_to_agent')
                                <span class="badge badge-warning">Pendiente Firma</span>
                            @elseif($interaction->evaluation->status === 'agent_responded')
                                <span class="badge badge-success">Firmada</span>
                            @elseif($interaction->evaluation->status === 'disputed')
                                <span class="badge badge-danger">En Disputa</span>
                            @else
                                <span class="badge badge-neutral">{{ ucfirst($interaction->evaluation->status) }}</span>
                            @endif
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
                            <button @click="showDebug = !showDebug" class="text-xs text-gray-500 hover:text-indigo-600 flex items-center gap-1 mx-auto">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                <span x-text="showDebug ? 'Ocultar Detalles Técnicos (Debug)' : 'Ver Detalles Técnicos (Debug)'"></span>
                            </button>
                            
                            <div x-show="showDebug" class="mt-4 space-y-4" style="display: none;">
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Prompt Enviado a IA</h4>
                                    <div class="bg-gray-800 rounded-lg p-3 overflow-x-auto">
                                        <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap">{{ $interaction->evaluation->ai_prompt ?? 'No registrado' }}</pre>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Respuesta Cruda de IA</h4>
                                    <div class="bg-gray-800 rounded-lg p-3 overflow-x-auto">
                                        <pre class="text-xs text-blue-400 font-mono whitespace-pre-wrap">{{ $interaction->evaluation->ai_raw_response ?? 'No registrado' }}</pre>
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
                            <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Pendiente de Evaluación</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Esta transcripción aún no ha sido evaluada por IA</p>
                            </div>
                        </div>
                        @if($interaction->campaign?->activeFormVersion)
                            <form method="POST" action="{{ route('transcripts.evaluate', $interaction) }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-md" onclick="this.disabled=true; this.innerHTML='Evaluando...'; this.form.submit();">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    Evaluar con IA
                                </button>
                            </form>
                        @else
                            <span class="text-sm text-rose-600 dark:text-rose-400">La campaña no tiene ficha de calidad activa</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div>
            <a href="{{ route('transcripts.index') }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Listado
            </a>
        </div>
    </div>
</x-app-layout>
