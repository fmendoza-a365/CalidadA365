<x-app-layout>
    <x-slot name="header">Transcripción #{{ $interaction->id }}</x-slot>

    <div class="space-y-6">
        <!-- Información General -->
        <div class="card">
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Campaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->campaign?->name ?? 'Campaña no disponible' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Asesor</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->agent?->name ?? 'Usuario eliminado' }}
                        </p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Supervisor</div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $interaction->supervisor?->name ?? '—' }}
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

        <!-- Audio Player (for audio sources) -->
        @if($interaction->isAudio())
            <div class="card">
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Audio Original</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $interaction->file_name }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <audio controls class="w-full" preload="metadata">
                        <source src="{{ route('transcripts.audio', $interaction) }}" type="audio/mpeg">
                        Tu navegador no soporta la reproducción de audio.
                    </audio>
                </div>
            </div>
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

        <!-- Análisis de Sentimiento -->
        @if($interaction->isAudio() && !empty($interaction->metadata['sentiment']))
            @php $sentiment = $interaction->metadata['sentiment']; @endphp
            <div class="card">
                <div class="card-header flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Análisis de Sentimiento</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $sentiment['summary'] ?? '' }}</p>
                    </div>
                    @php
                        $overallSentiment = $sentiment['overall'] ?? 'neutro';
                        $sentimentColors = [
                            'positivo' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400',
                            'negativo' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400',
                            'neutro' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-400',
                            'mixto' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
                        ];
                        $sentimentEmojis = ['positivo' => '😊', 'negativo' => '😟', 'neutro' => '😐', 'mixto' => '🔄'];
                    @endphp
                    <span
                        class="ml-auto px-3 py-1 rounded-full text-sm font-medium {{ $sentimentColors[$overallSentiment] ?? $sentimentColors['neutro'] }}">
                        {{ $sentimentEmojis[$overallSentiment] ?? '😐' }} {{ ucfirst($overallSentiment) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Agente -->
                        @if(!empty($sentiment['agent']))
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">Agente</span>
                                    @php
                                        $agentScore = $sentiment['agent']['score'] ?? 0;
                                        $agentColor = $agentScore > 0.3 ? 'text-emerald-600' : ($agentScore < -0.3 ? 'text-rose-600' : 'text-gray-600');
                                    @endphp
                                    <span
                                        class="ml-auto text-sm font-mono {{ $agentColor }}">{{ number_format($agentScore, 1) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
                                    <div class="h-2 rounded-full transition-all {{ $agentScore > 0.3 ? 'bg-emerald-500' : ($agentScore < -0.3 ? 'bg-rose-500' : 'bg-amber-500') }}"
                                        style="width: {{ (($agentScore + 1) / 2) * 100 }}%"></div>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $sentiment['agent']['tone'] ?? '' }}</p>
                            </div>
                        @endif

                        <!-- Cliente -->
                        @if(!empty($sentiment['client']))
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">Cliente</span>
                                    @php
                                        $clientScore = $sentiment['client']['score'] ?? 0;
                                        $clientColor = $clientScore > 0.3 ? 'text-emerald-600' : ($clientScore < -0.3 ? 'text-rose-600' : 'text-gray-600');
                                        $satisfaction = $sentiment['client']['satisfaction'] ?? 'neutro';
                                        $satColors = ['satisfecho' => 'badge-success', 'insatisfecho' => 'badge-danger', 'neutro' => 'badge-warning'];
                                    @endphp
                                    <span
                                        class="ml-auto text-sm font-mono {{ $clientColor }}">{{ number_format($clientScore, 1) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
                                    <div class="h-2 rounded-full transition-all {{ $clientScore > 0.3 ? 'bg-emerald-500' : ($clientScore < -0.3 ? 'bg-rose-500' : 'bg-amber-500') }}"
                                        style="width: {{ (($clientScore + 1) / 2) * 100 }}%"></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $sentiment['client']['tone'] ?? '' }}
                                    </p>
                                    <span
                                        class="badge {{ $satColors[$satisfaction] ?? 'badge-warning' }}">{{ ucfirst($satisfaction) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Contenido de la Transcripción -->
        @if(!$interaction->isTranscribing() && !empty($interaction->transcript_text))
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ $interaction->isAudio() ? 'Transcripción del Audio' : 'Contenido de la Transcripción' }}
                    </h3>
                </div>
                <div class="card-body">
                    @if($interaction->isAudio())
                        {{-- Chat bubble format for audio transcripts --}}
                            @php
                                // Robust parsing: split by [MM:SS] Speaker: pattern
                                $lines = preg_split('/(?=\[?(?:\d{1,2}:\d{2})\]?\s*(?:Agente|Cliente):)/i', $interaction->transcript_text, -1, PREG_SPLIT_NO_EMPTY);
                                
                                // Fallback
                                if (count($lines) <= 1 && str_contains($interaction->transcript_text, "\n")) {
                                    $lines = explode("\n", $interaction->transcript_text);
                                }
                            @endphp
                            @foreach($lines as $line)
                                @php
                                    $line = trim($line);
                                    if (empty($line) || strlen($line) < 3) continue; // Skip artifacts
                                    
                                    $isAgent = stripos($line, 'Agente') !== false;
                                    $isClient = stripos($line, 'Cliente') !== false;
                                    
                                    // Extract timestamp (more flexible regex - with or without brackets)
                                    preg_match('/(?:\[|\()?(\d{1,2}:\d{2})(?:\]|\))?/', $line, $timeMatch);
                                    $timestamp = $timeMatch[1] ?? '';
                                    
                                    // Clean message
                                    // 1. Remove timestamp [00:00] or 00:00] or [00:00
                                    $message = preg_replace('/^\[?\d{1,2}:\d{2}\]?\s*/', '', $line);
                                    // 2. Remove Speaker Label (Agente: / Cliente:)
                                    $message = preg_replace('/^(?:Agente|Cliente):?\s*/i', '', $message);
                                    // 3. Remove leading/trailing quotes or junk
                                    $message = trim($message, " \"'\n\r\t\v\0");
                                @endphp
                                
                                @if($isAgent)
                                    <div class="flex gap-3 items-start group">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0 mt-1">
                                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        <div class="flex flex-col items-start max-w-[85%]">
                                            <div class="flex items-center gap-2 mb-1 ml-1">
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Agente</span>
                                                @if($timestamp)
                                                    <span class="text-[10px] text-gray-400 font-mono">{{ $timestamp }}</span>
                                                @endif
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm">
                                                <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap">{{ $message }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($isClient)
                                    <div class="flex gap-3 items-start justify-end group">
                                        <div class="flex flex-col items-end max-w-[85%]">
                                            <div class="flex items-center gap-2 mb-1 mr-1">
                                                @if($timestamp)
                                                    <span class="text-[10px] text-gray-400 font-mono">{{ $timestamp }}</span>
                                                @endif
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Cliente</span>
                                            </div>
                                            <div class="bg-indigo-600 dark:bg-indigo-600 rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-sm">
                                                <p class="text-sm text-white leading-relaxed whitespace-pre-wrap">{{ $message }}</p>
                                            </div>
                                        </div>
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0 mt-1">
                                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                @else
                                    @if(strlen($line) > 5)
                                        <div class="flex justify-center my-2">
                                            <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full italic">{{ $line }}</span>
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @else
                        {{-- Plain text format for .txt uploads --}}
                        {{-- Plain text format for .txt uploads --}}
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 max-h-96 overflow-y-auto scrollbar-thin">
                            <pre
                                class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 font-mono leading-relaxed">{{ $interaction->transcript_text }}</pre>
                        </div>
                    @endif
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
                            <form method="POST" action="{{ route('transcripts.evaluate', $interaction) }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-md"
                                    onclick="this.disabled=true; this.innerHTML='Evaluando...'; this.form.submit();">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    Evaluar con IA
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