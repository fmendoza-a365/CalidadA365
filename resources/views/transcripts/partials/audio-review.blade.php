@php
    $timeline = $timeline ?? [];
    $summary = $timeline['summary'] ?? [];
    $sentiment = $timeline['sentiment'] ?? null;
    $segments = $timeline['segments'] ?? [];
    $bars = $timeline['bars'] ?? [];
    $voice = $summary['voice'] ?? [];
    $qualitySignals = $summary['quality_signals'] ?? [];
    $eventSegments = collect($segments)
        ->filter(function ($segment, $index) use ($segments) {
            $previous = $segments[$index - 1] ?? null;
            $score = abs((float) ($segment['score'] ?? 0));
            $sentiment = $segment['sentiment'] ?? 'neutro';
            $emotion = $segment['emotion'] ?? 'calma';
            $isNeutral = $sentiment === 'neutro'
                && in_array($emotion, ['calma', 'neutro', 'neutral'], true)
                && $score < 0.35;

            if ($isNeutral) {
                return false;
            }

            return $index === 0
                || $score >= 0.35
                || ($segment['sentiment'] ?? null) !== ($previous['sentiment'] ?? null)
                || ($segment['emotion'] ?? null) !== ($previous['emotion'] ?? null);
        })
        ->take(48)
        ->values();
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
        default => filled($value) ? str($value)->replace('_', ' ')->title()->toString() : 'No detectado',
    };
    $signalClass = fn ($value) => match ($value) {
        'fortaleza', 'bajo', 'claro' => 'text-emerald-600 dark:text-emerald-300',
        'riesgo', 'alto', 'bajo_claridad' => 'text-rose-600 dark:text-rose-300',
        'medio', 'regular', 'variable' => 'text-amber-600 dark:text-amber-300',
        default => 'text-gray-900 dark:text-white',
    };
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
                <button type="button" class="relative block h-3 w-full rounded-full bg-white/10"
                    @click="seekFromTrack($event)" aria-label="Cambiar posición del audio">
                    <template x-for="segment in segments" :key="`track-${segment.id}`">
                        <span class="absolute top-0 h-full opacity-45"
                            :style="`left: ${segment.left}%; width: ${segment.width}%; background-color: ${segment.color};`"></span>
                    </template>
                    <span class="absolute left-0 top-0 h-full rounded-full bg-white"
                        :style="`width: ${progress}%;`"></span>
                    <span class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-gray-950 bg-white shadow"
                        :style="`left: ${progress}%;`"></span>
                </button>

                <div class="relative mt-4">
                    <div class="relative h-32 cursor-pointer overflow-hidden rounded-lg bg-white/5 px-2"
                        @click="seekFromWaveform($event)">
                        <div class="pointer-events-none absolute inset-x-2 top-1/2 h-px bg-white/10"></div>

                        <div class="relative flex h-full items-center gap-[3px] pb-4 pt-7">
                            <template x-for="bar in visualBars" :key="`bar-${bar.index}`">
                                <button type="button" class="flex h-full flex-1 items-center justify-center rounded-sm transition hover:opacity-100"
                                    :title="formatTime(bar.time)"
                                    @click.stop="seek(bar.time)">
                                    <span class="block min-h-[4px] w-full rounded-full opacity-65 transition"
                                        :class="{ 'opacity-100': bar.time <= currentTime }"
                                        :style="`height: ${bar.height}%; background-color: ${bar.time <= currentTime ? bar.color : '#5f6b7c'};`">
                                    </span>
                                </button>
                            </template>
                        </div>

                        <div class="pointer-events-none absolute inset-x-2 bottom-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <template x-for="segment in segments" :key="`sentiment-${segment.id}`">
                                <span class="absolute top-0 h-full"
                                    :style="`left: ${segment.left}%; width: ${segment.width}%; background-color: ${segment.color};`"></span>
                            </template>
                            <span class="absolute left-0 top-0 h-full bg-white/90"
                                :style="`width: ${progress}%;`"></span>
                        </div>

                        @if($eventSegments->isNotEmpty())
                            <div class="absolute inset-x-2 top-2 z-20 h-7">
                                @foreach($eventSegments as $segment)
                                    <button type="button"
                                        class="absolute top-0 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-gray-950 text-white shadow ring-1 ring-white/20 transition hover:scale-110 hover:ring-white"
                                        :class="activeTurnId === '{{ $segment['turn_id'] ?? '' }}' ? 'ring-2 ring-white' : ''"
                                        style="left: {{ $segment['left'] ?? 0 }}%; background-color: {{ $segment['color'] ?? '#64748b' }};"
                                        title="{{ ($segment['start_label'] ?? '00:00').' · '.($segment['label'] ?? 'Evento').' · '.($segment['emotion_label'] ?? 'Evento') }}"
                                        @click.stop="selectTurn({{ (int) ($segment['start'] ?? 0) }}, @js($segment['turn_id'] ?? null))">
                                        @include('transcripts.partials.emotion-icon', [
                                            'icon' => $segment['emotion_icon'] ?? 'wave',
                                            'class' => 'h-3.5 w-3.5',
                                        ])
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="pointer-events-none absolute inset-x-2 bottom-0 top-0 z-10">
                            <div class="absolute bottom-0 top-0 w-px bg-white shadow-[0_0_12px_rgba(255,255,255,0.75)]"
                                :style="`left: ${progress}%;`"></div>
                        </div>
                    </div>
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
                </div>
            </div>
        </div>

        @if($sentiment)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">Resumen emocional</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $sentiment['summary'] ?? 'Análisis por tramo de la llamada.' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $overallBadgeClass }}">
                            {{ ucfirst($overallSentiment) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach(['agent' => 'Agente', 'client' => 'Cliente'] as $key => $label)
                            @if(!empty($sentiment[$key]))
                                @php
                                    $score = (float) ($sentiment[$key]['score'] ?? 0);
                                    $width = (($score + 1) / 2) * 100;
                                @endphp
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</span>
                                        <span class="font-mono text-xs text-gray-500">{{ number_format($score, 1) }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-800">
                                        <div class="h-2 rounded-full {{ $score >= 0.3 ? 'bg-emerald-500' : ($score <= -0.3 ? 'bg-rose-500' : 'bg-amber-500') }}"
                                            style="width: {{ $width }}%"></div>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $sentiment[$key]['tone'] ?? '' }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Lectura rápida</h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Emoción dominante</span>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
                                @include('transcripts.partials.emotion-icon', [
                                    'icon' => $summary['dominant_emotion_icon'] ?? 'minus',
                                    'class' => 'h-3.5 w-3.5',
                                ])
                                {{ $summary['dominant_emotion_label'] ?? 'Calma' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Cambios de turno</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $summary['handoffs'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Participación agente</span>
                            <span class="font-semibold text-emerald-600">{{ $summary['agent_talk_percent'] ?? 0 }}%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Participación cliente</span>
                            <span class="font-semibold text-indigo-600">{{ $summary['client_talk_percent'] ?? 0 }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Señales de voz</h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Ritmo general</span>
                            <span class="font-semibold {{ $signalClass($voice['overall_pace'] ?? null) }}">{{ $voice['overall_pace_label'] ?? 'No detectado' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Velocidad agente</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['agent_speech_rate_wpm'] ?? 0 }} ppm</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Velocidad cliente</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['client_speech_rate_wpm'] ?? 0 }} ppm</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Claridad</span>
                            <span class="font-semibold {{ $signalClass($voice['clarity'] ?? null) }}">{{ $signalLabel($voice['clarity'] ?? null) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Interrupciones</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $voice['interruptions'] ?? 0 }}</span>
                        </div>
                    </div>
                    @if(!empty($voice['notes']))
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $voice['notes'] }}</p>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Impacto en calidad</h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Empatía</span>
                            <span class="font-semibold {{ $signalClass($qualitySignals['empathy'] ?? null) }}">{{ $signalLabel($qualitySignals['empathy'] ?? null) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Objeciones</span>
                            <span class="font-semibold {{ $signalClass($qualitySignals['objection_handling'] ?? null) }}">{{ $signalLabel($qualitySignals['objection_handling'] ?? null) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Riesgo experiencia</span>
                            <span class="font-semibold {{ $signalClass($qualitySignals['customer_experience_risk'] ?? null) }}">{{ $signalLabel($qualitySignals['customer_experience_risk'] ?? null) }}</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $qualitySignals['summary'] ?? 'Señales listas para apoyar la evaluación de calidad.' }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Tramo activo</h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Tiempo</span>
                            <span class="font-mono font-semibold text-gray-900 dark:text-white" x-text="activeSegment?.start_label || '00:00'">00:00</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Emoción</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="activeSegment?.emotion_label || 'Sin tramo'">Sin tramo</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Ritmo</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="activeSegment?.pace_label || 'No detectado'">No detectado</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Velocidad</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="activeSegment?.speech_rate_wpm ? `${activeSegment.speech_rate_wpm} ppm` : 'No detectado'">No detectado</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" x-text="activeSegment?.evidence || activeSegment?.voice_tone || 'Selecciona un tramo del audio para ver su lectura emocional.'">
                        Selecciona un tramo del audio para ver su lectura emocional.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
