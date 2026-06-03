@php
    $timeline = $timeline ?? [];
    $summary = $timeline['summary'] ?? [];
    $sentiment = $timeline['sentiment'] ?? null;
    $segments = $timeline['segments'] ?? [];
    $bars = $timeline['bars'] ?? [];
    $voice = $summary['voice'] ?? [];
    $silence = $summary['silence'] ?? [];
    $qualitySignals = $summary['quality_signals'] ?? [];
    $emotionLegend = collect($segments)
        ->unique(fn ($segment) => $segment['emotion'] ?? $segment['emotion_label'] ?? '')
        ->take(6)
        ->values();
    $overallSentiment = $sentiment['overall'] ?? $summary['overall_sentiment'] ?? 'neutro';
    $overallBadgeClass = match ($overallSentiment) {
        'positivo' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        'negativo' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        'mixto' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
    $signalLabel = fn ($value) => match ($value) {
        'fortaleza' => 'Fortaleza',
        'riesgo' => 'Riesgo',
        'alto' => 'Alto',
        'medio', 'media' => 'Medio',
        'bajo', 'baja' => 'Bajo',
        'normal' => 'Normal',
        'rapido' => 'Rápido',
        'pausado' => 'Pausado',
        'variable' => 'Variable',
        'claro' => 'Claro',
        'regular' => 'Regular',
        'balanced' => 'Balanceado',
        'agent_dominant' => 'Dominio agente',
        'client_dominant' => 'Dominio cliente',
        'recupera' => 'Recupera',
        'contiene' => 'Contiene',
        'empeora' => 'Empeora',
        'sin_riesgo' => 'Sin riesgo',
        default => filled($value) ? str($value)->replace('_', ' ')->title()->toString() : 'No detectado',
    };
    $signalClass = fn ($value) => match ($value) {
        'fortaleza', 'bajo', 'claro', 'recupera', 'sin_riesgo', 'alto_control', 'balanced' => 'text-emerald-600 dark:text-emerald-300',
        'riesgo', 'alto', 'bajo_claridad', 'empeora', 'client_dominant' => 'text-rose-600 dark:text-rose-300',
        'medio', 'regular', 'variable', 'contiene', 'agent_dominant' => 'text-amber-600 dark:text-amber-300',
        default => 'text-gray-900 dark:text-white',
    };
    $isDetected = fn ($value) => filled($value)
        && ! in_array(str($value)->lower()->replace('_', ' ')->trim()->toString(), ['no detectado', 'n/a', 'na', 'null'], true);
    $feedbackIndicators = [
        'empathy' => 'Empatía',
        'active_listening' => 'Escucha activa',
        'objection_handling' => 'Objeciones',
        'resolution_clarity' => 'Claridad solución',
        'script_control' => 'Control del speech',
        'closing_quality' => 'Cierre',
    ];
    $visibleFeedbackIndicators = collect($feedbackIndicators)
        ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'value' => $qualitySignals[$key] ?? null])
        ->filter(fn ($item) => $isDetected($item['value']))
        ->values();
    $criticalMoments = collect($qualitySignals['critical_moments'] ?? [])
        ->filter(fn ($moment) => is_array($moment))
        ->take(4);
    $coachingRecommendations = collect($qualitySignals['coaching_recommendations'] ?? [])
        ->filter(fn ($recommendation) => is_array($recommendation))
        ->take(4);
    $supervisorAlerts = collect($qualitySignals['supervisor_alerts'] ?? [])
        ->filter(fn ($alert) => is_array($alert))
        ->take(3);
@endphp

<div class="card overflow-hidden">
    <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white shadow-sm dark:bg-white dark:text-gray-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-gray-900 dark:text-white">Audio de la interacción</h3>
                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $interaction->file_name }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                    <div class="text-[11px] font-semibold uppercase text-gray-400">Duración</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="durationLabel">
                        {{ $summary['duration_label'] ?? $timeline['duration_label'] ?? '00:00' }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                    <div class="text-[11px] font-semibold uppercase text-gray-400">Agente</div>
                    <div class="text-sm font-semibold text-emerald-600">{{ $summary['agent_talk_label'] ?? '00:00' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                    <div class="text-[11px] font-semibold uppercase text-gray-400">Cliente</div>
                    <div class="text-sm font-semibold text-indigo-600">{{ $summary['client_talk_label'] ?? '00:00' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                    <div class="text-[11px] font-semibold uppercase text-gray-400">Tendencia</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $summary['trend'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body space-y-5">
        <audio x-ref="audio" preload="metadata" class="hidden" @loadedmetadata="onLoadedMetadata"
            @timeupdate="onTimeUpdate" @ended="onEnded">
            <source src="{{ route('transcripts.audio', $interaction) }}">
            Tu navegador no soporta la reproducción de audio.
        </audio>

        <div class="rounded-xl bg-gray-950 p-4 text-white shadow-sm">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" @click="toggle"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-950 shadow-sm transition hover:bg-gray-100">
                        <svg x-show="!playing" class="h-5 w-5 translate-x-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.5 5.2v9.6c0 .7.8 1.1 1.4.7l7.4-4.8a.8.8 0 000-1.4L7.9 4.5c-.6-.4-1.4 0-1.4.7z" />
                        </svg>
                        <svg x-show="playing" x-cloak class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6 4h2.5v12H6V4zm5.5 0H14v12h-2.5V4z" />
                        </svg>
                    </button>
                    <div>
                        <div class="font-mono text-sm"><span x-text="currentLabel">00:00</span> / <span x-text="durationLabel">{{ $timeline['duration_label'] ?? '00:00' }}</span></div>
                        <div class="text-xs text-gray-400" x-text="activeSegmentLabel">Sin segmento activo</div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <select class="rounded-lg border border-white/10 bg-white/10 px-2 py-1 text-sm text-white focus:border-white/30 focus:ring-0"
                        x-model="speed" @change="setSpeed(speed)">
                        <option class="text-gray-900" value="0.75">0.75x</option>
                        <option class="text-gray-900" value="1">1x</option>
                        <option class="text-gray-900" value="1.25">1.25x</option>
                        <option class="text-gray-900" value="1.5">1.5x</option>
                        <option class="text-gray-900" value="2">2x</option>
                    </select>
                    <button type="button" @click="seek(0)"
                        class="rounded-lg border border-white/10 px-3 py-1 text-sm text-gray-200 transition hover:bg-white/10">
                        Reiniciar
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-black/25 p-3">
                <div class="mb-2 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <span>Línea de tiempo</span>
                    <span class="font-mono normal-case tracking-normal text-gray-300">
                        <span x-text="currentLabel">00:00</span> / <span x-text="durationLabel">{{ $timeline['duration_label'] ?? '00:00' }}</span>
                    </span>
                </div>

                <div class="relative h-36 cursor-pointer overflow-hidden rounded-lg border border-white/10 bg-white/5"
                    data-audio-timeline-track
                    @click="seekFromTimeline($event)">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-white/15">
                        <div class="h-full bg-emerald-400" :style="`width: ${progress}%;`"></div>
                    </div>

                    <div class="pointer-events-none absolute inset-x-0 top-[64px] h-px bg-white/10"></div>

                    <div class="pointer-events-none absolute inset-x-0 bottom-8 top-9 z-[1]" x-show="silentSegments.length" x-cloak>
                        <template x-for="silence in silentSegments" :key="silence.id">
                            <span class="absolute inset-y-0 rounded-sm bg-amber-400/15 ring-1 ring-amber-300/25"
                                :style="`left: ${silence.left}%; width: ${silence.width}%;`"
                                :title="`Tiempo muerto ${silence.start_label}-${silence.end_label}`"></span>
                        </template>
                    </div>

                    <div class="pointer-events-none absolute inset-x-0 bottom-8 top-9 flex items-center gap-[3px]">
                        <template x-for="bar in visualBars" :key="`bar-${bar.index}`">
                            <span class="flex h-full flex-1 items-center justify-center rounded-sm">
                                <span class="block min-h-[4px] w-full rounded-full opacity-70 transition"
                                    :class="{ 'opacity-100': bar.time <= currentTime }"
                                    :style="`height: ${bar.height}%; background-color: ${bar.time <= currentTime ? bar.color : '#5f6b7c'};`">
                                </span>
                            </span>
                        </template>
                    </div>

                    <div class="pointer-events-none absolute inset-x-0 bottom-4 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <template x-for="segment in segments" :key="`sentiment-${segment.id}`">
                            <span class="absolute top-0 h-full"
                                :style="`left: ${segment.left}%; width: ${segment.width}%; background-color: ${segment.color};`"></span>
                        </template>
                    </div>

                    <div class="absolute inset-x-0 top-3 z-20 h-7" x-show="eventSegments.length" x-cloak>
                        <template x-for="segment in eventSegments" :key="`event-${segment.id}`">
                            <button type="button"
                                class="absolute top-0 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-gray-950 text-white shadow ring-1 ring-white/20 transition hover:scale-110 hover:ring-white"
                                :class="activeTurnId === segment.turn_id ? 'ring-2 ring-white' : ''"
                                :style="`left: ${markerLeft(segment)}%; background-color: ${segment.color || '#64748b'};`"
                                :title="eventTitle(segment)"
                                @click.stop="selectSegment(segment)">
                                <svg x-show="segment.emotion_icon === 'alert'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 4.3 2.8 17.7A1.5 1.5 0 0 0 4.1 20h15.8a1.5 1.5 0 0 0 1.3-2.3L13.7 4.3a1.5 1.5 0 0 0-2.4 0Z" />
                                </svg>
                                <svg x-show="segment.emotion_icon === 'question'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9a3.5 3.5 0 1 1 5.2 3.1c-1.2.7-2.2 1.4-2.2 3.1M12 19h.01" />
                                </svg>
                                <svg x-show="segment.emotion_icon === 'minus'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12" />
                                </svg>
                                <svg x-show="!['alert', 'question', 'minus'].includes(segment.emotion_icon)" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                </svg>
                            </button>
                        </template>
                    </div>

                    <div class="pointer-events-none absolute inset-y-0 z-10 w-px bg-white shadow-[0_0_12px_rgba(255,255,255,0.75)]"
                        :style="`left: ${progress}%;`"></div>
                    <div class="pointer-events-none absolute top-0 z-10 h-3 w-3 -translate-x-1/2 rounded-full border-2 border-gray-950 bg-white shadow"
                        :style="`left: ${progress}%;`"></div>
                </div>

                <div class="mt-3 grid grid-cols-[72px_1fr] gap-2 text-xs">
                    <div class="font-semibold text-emerald-300">Agente</div>
                    <div class="relative h-5 rounded bg-white/5">
                        <template x-for="segment in segments.filter((item) => item.speaker === 'agent')" :key="segment.id">
                            <button type="button" class="absolute top-1 h-3 rounded-full bg-emerald-400/80"
                                :style="`left: ${segment.left}%; width: ${segment.width}%;`"
                                @click="selectSegment(segment)"
                                :title="`${segment.start_label} · ${segment.emotion_label}`">
                            </button>
                        </template>
                    </div>
                    <div class="font-semibold text-indigo-300">Cliente</div>
                    <div class="relative h-5 rounded bg-white/5">
                        <template x-for="segment in segments.filter((item) => item.speaker === 'client')" :key="segment.id">
                            <button type="button" class="absolute top-1 h-3 rounded-full bg-indigo-400/80"
                                :style="`left: ${segment.left}%; width: ${segment.width}%;`"
                                @click="selectSegment(segment)"
                                :title="`${segment.start_label} · ${segment.emotion_label}`">
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-300">
                    <span class="font-semibold uppercase tracking-wide text-gray-500">Emociones</span>
                @forelse($emotionLegend as $segment)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-1">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-white"
                            style="background-color: {{ $segment['color'] ?? '#64748b' }};">
                            @include('transcripts.partials.emotion-icon', [
                                'icon' => $segment['emotion_icon'] ?? 'wave',
                                'class' => 'h-3 w-3',
                            ])
                        </span>
                        {{ $segment['emotion_label'] ?? 'Evento' }}
                    </span>
                @empty
                    <span>Sin eventos detectados</span>
                @endforelse
                @if(($silence['long_pauses'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300/20 bg-amber-400/10 px-2.5 py-1 text-amber-200">
                        <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                        Tiempo muerto {{ $silence['total_label'] ?? '00:00' }}
                    </span>
                @endif
                </div>
            </div>
        </div>

        @if($sentiment)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
                <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Resumen emocional</h4>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $sentiment['summary'] ?? 'Análisis por tramo de la llamada.' }}</p>
                        </div>
                        <span class="w-fit rounded-full px-3 py-1 text-sm font-semibold {{ $overallBadgeClass }}">
                            {{ ucfirst($overallSentiment) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach(['agent' => 'Agente', 'client' => 'Cliente'] as $key => $label)
                            @if(!empty($sentiment[$key]))
                                @php
                                    $score = (float) ($sentiment[$key]['score'] ?? 0);
                                    $width = max(0, min(100, (($score + 1) / 2) * 100));
                                @endphp
                                <div class="space-y-2 rounded-lg bg-gray-50 px-3 py-3 dark:bg-gray-800/45">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</span>
                                        <span class="font-mono text-xs text-gray-500">{{ number_format($score, 1) }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-800">
                                        <div class="h-2 rounded-full {{ $score >= 0.3 ? 'bg-emerald-500' : ($score <= -0.3 ? 'bg-rose-500' : 'bg-amber-500') }}"
                                            style="width: {{ $width }}%"></div>
                                    </div>
                                    @if(!empty($sentiment[$key]['tone']))
                                        <p class="text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $sentiment[$key]['tone'] }}</p>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="font-semibold text-gray-900 dark:text-white">Lectura rápida</h4>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Emoción dominante</span>
                            <span class="inline-flex min-w-0 items-center gap-1.5 text-right font-semibold text-gray-900 dark:text-white">
                                @include('transcripts.partials.emotion-icon', [
                                    'icon' => $summary['dominant_emotion_icon'] ?? 'minus',
                                    'class' => 'h-3.5 w-3.5 flex-shrink-0',
                                ])
                                <span class="truncate">{{ $summary['dominant_emotion_label'] ?? 'Calma' }}</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Riesgo experiencia</span>
                            <span class="font-semibold {{ $signalClass($qualitySignals['customer_experience_risk'] ?? null) }}">{{ $signalLabel($qualitySignals['customer_experience_risk'] ?? null) }}</span>
                        </div>
                        @if($isDetected($qualitySignals['emotional_recovery'] ?? null))
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-gray-500 dark:text-gray-400">Recuperación emocional</span>
                                <span class="font-semibold {{ $signalClass($qualitySignals['emotional_recovery'] ?? null) }}">{{ $signalLabel($qualitySignals['emotional_recovery'] ?? null) }}</span>
                            </div>
                        @endif
                        @if(($silence['long_pauses'] ?? 0) > 0)
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-gray-500 dark:text-gray-400">Tiempo muerto</span>
                                <span class="font-semibold text-amber-600 dark:text-amber-300">{{ $silence['total_label'] ?? '00:00' }}</span>
                            </div>
                        @endif
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/45">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Agente</div>
                                <div class="mt-1 font-semibold text-emerald-600">{{ $summary['agent_talk_percent'] ?? 0 }}%</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/45">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Cliente</div>
                                <div class="mt-1 font-semibold text-indigo-600">{{ $summary['client_talk_percent'] ?? 0 }}%</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <details class="rounded-xl border border-gray-200 dark:border-gray-800">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 marker:hidden">
                    <div class="min-w-0">
                        <h4 class="font-semibold text-gray-900 dark:text-white">Señales y feedback</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Detalle técnico para sustentar el feedback del monitor.</p>
                    </div>
                    <span class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 dark:border-gray-800 dark:text-gray-300">Ver detalle</span>
                </summary>

                <div class="grid grid-cols-1 gap-5 border-t border-gray-200 px-4 py-4 dark:border-gray-800 lg:grid-cols-2">
                    <section>
                        <h5 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Señales de voz</h5>
                        <div class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            @if($isDetected($voice['overall_pace_label'] ?? $voice['overall_pace'] ?? null))
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Ritmo general</span>
                                    <span class="font-semibold {{ $signalClass($voice['overall_pace'] ?? null) }}">{{ $voice['overall_pace_label'] ?? $signalLabel($voice['overall_pace'] ?? null) }}</span>
                                </div>
                            @endif
                            @if(($voice['agent_speech_rate_wpm'] ?? 0) > 0)
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Velocidad agente</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['agent_speech_rate_wpm'] }} ppm</span>
                                </div>
                            @endif
                            @if(($voice['client_speech_rate_wpm'] ?? 0) > 0)
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Velocidad cliente</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['client_speech_rate_wpm'] }} ppm</span>
                                </div>
                            @endif
                            @if($isDetected($voice['clarity'] ?? null))
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Claridad</span>
                                    <span class="font-semibold {{ $signalClass($voice['clarity'] ?? null) }}">{{ $signalLabel($voice['clarity'] ?? null) }}</span>
                                </div>
                            @endif
                            @if(($voice['interruptions'] ?? 0) > 0)
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Interrupciones</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['interruptions'] }}</span>
                                </div>
                            @endif
                            @if(($voice['long_pauses'] ?? 0) > 0)
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Pausas largas</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['long_pauses'] }}</span>
                                </div>
                            @endif
                            @if(($silence['total_seconds'] ?? 0) > 0)
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Tiempo muerto total</span>
                                    <span class="font-semibold text-amber-600 dark:text-amber-300">{{ $silence['total_label'] ?? '00:00' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Porcentaje de silencio</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $silence['ratio_percent'] ?? 0 }}%</span>
                                </div>
                                @if(!empty($silence['longest_label']))
                                    <div class="flex items-center justify-between gap-4 py-2">
                                        <span class="text-gray-500 dark:text-gray-400">Silencio más largo</span>
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $silence['longest_label'] }}</span>
                                    </div>
                                @endif
                            @endif
                            @if($isDetected($voice['talk_balance'] ?? null))
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Balance conversación</span>
                                    <span class="font-semibold {{ $signalClass($voice['talk_balance'] ?? null) }}">{{ $signalLabel($voice['talk_balance'] ?? null) }}</span>
                                </div>
                            @endif
                        </div>
                        @if(!empty($voice['talk_balance_note']))
                            <p class="mt-3 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $voice['talk_balance_note'] }}</p>
                        @endif
                        @if(!empty($voice['notes']))
                            <p class="mt-3 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $voice['notes'] }}</p>
                        @endif
                    </section>

                    <section>
                        <h5 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Indicadores de feedback</h5>
                        @if($visibleFeedbackIndicators->isNotEmpty())
                            <div class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                @foreach($visibleFeedbackIndicators as $indicator)
                                    <div class="flex items-center justify-between gap-4 py-2">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $indicator['label'] }}</span>
                                        <span class="font-semibold {{ $signalClass($indicator['value']) }}">{{ $signalLabel($indicator['value']) }}</span>
                                    </div>
                                @endforeach
                                @if($isDetected($qualitySignals['agent_control'] ?? null))
                                    <div class="flex items-center justify-between gap-4 py-2">
                                        <span class="text-gray-500 dark:text-gray-400">Control agente</span>
                                        <span class="font-semibold {{ $signalClass($qualitySignals['agent_control'] ?? null) }}">{{ $signalLabel($qualitySignals['agent_control'] ?? null) }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between gap-4 py-2">
                                    <span class="text-gray-500 dark:text-gray-400">Cliente queda sin resolver</span>
                                    <span class="font-semibold {{ !empty($qualitySignals['customer_left_unresolved']) ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                                        {{ !empty($qualitySignals['customer_left_unresolved']) ? 'Sí' : 'No' }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800/45 dark:text-gray-400">
                                Los indicadores detallados estarán disponibles en nuevos análisis o al reanalizar este audio.
                            </p>
                        @endif

                        @if($isDetected($qualitySignals['frustration_cause'] ?? null))
                            <p class="mt-3 text-sm leading-5 text-gray-500 dark:text-gray-400"><span class="font-semibold text-gray-700 dark:text-gray-300">Causa:</span> {{ $qualitySignals['frustration_cause'] }}</p>
                        @endif
                        @if(!empty($qualitySignals['summary']))
                            <p class="mt-3 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $qualitySignals['summary'] }}</p>
                        @endif
                    </section>
                </div>
            </details>

            @if($supervisorAlerts->isNotEmpty() || $criticalMoments->isNotEmpty() || $coachingRecommendations->isNotEmpty())
                <details class="rounded-xl border border-gray-200 dark:border-gray-800">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 marker:hidden">
                        <div class="min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Momentos críticos y coaching</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Alertas y recomendaciones para trabajar con el asesor.</p>
                        </div>
                        <span class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 dark:border-gray-800 dark:text-gray-300">Ver detalle</span>
                    </summary>

                    <div class="grid grid-cols-1 gap-5 border-t border-gray-200 px-4 py-4 dark:border-gray-800 xl:grid-cols-3">
                        @if($supervisorAlerts->isNotEmpty())
                            <section>
                                <h5 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Alertas supervisor</h5>
                                <div class="space-y-2">
                                    @foreach($supervisorAlerts as $alert)
                                        <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm dark:bg-rose-500/10">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold text-rose-700 dark:text-rose-300">{{ $alert['label'] ?? $signalLabel($alert['level'] ?? null) }}</span>
                                                <span class="text-xs uppercase tracking-wide text-rose-500">{{ $signalLabel($alert['level'] ?? null) }}</span>
                                            </div>
                                            <p class="mt-1 text-rose-700/80 dark:text-rose-200/80">{{ $alert['message'] ?? 'Alerta operativa detectada.' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($criticalMoments->isNotEmpty())
                            <section class="{{ $supervisorAlerts->isEmpty() ? 'xl:col-span-2' : '' }}">
                                <h5 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Momentos críticos</h5>
                                <div class="space-y-2">
                                    @foreach($criticalMoments as $moment)
                                        @php
                                            $momentType = $moment['type'] ?? 'oportunidad';
                                            $momentClass = match ($momentType) {
                                                'riesgo' => 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10',
                                                'fortaleza' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10',
                                                default => 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10',
                                            };
                                        @endphp
                                        <div class="rounded-lg border px-3 py-2 text-sm {{ $momentClass }}">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $moment['title'] ?? 'Momento relevante' }}</span>
                                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $moment['label'] ?? '' }}</span>
                                            </div>
                                            @if(!empty($moment['evidence']))
                                                <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $moment['evidence'] }}</p>
                                            @endif
                                            @if(!empty($moment['feedback']))
                                                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $moment['feedback'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($coachingRecommendations->isNotEmpty())
                            <section class="{{ $supervisorAlerts->isEmpty() && $criticalMoments->isEmpty() ? 'xl:col-span-3' : '' }}">
                                <h5 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Recomendaciones coaching</h5>
                                <div class="space-y-2">
                                    @foreach($coachingRecommendations as $recommendation)
                                        <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-800">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ ucfirst($recommendation['skill'] ?? 'Habilidad') }}</span>
                                                <span class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst($recommendation['priority'] ?? 'media') }}</span>
                                            </div>
                                            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $recommendation['recommendation'] ?? 'Reforzar conducta observable.' }}</p>
                                            @if(!empty($recommendation['example']))
                                                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $recommendation['example'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </details>
            @endif
        @endif
    </div>
</div>
