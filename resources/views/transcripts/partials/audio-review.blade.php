@php
    $timeline = $timeline ?? [];
    $summary = $timeline['summary'] ?? [];
    $sentiment = $timeline['sentiment'] ?? null;
    $segments = $timeline['segments'] ?? [];
    $bars = $timeline['bars'] ?? [];
    $eventSegments = collect($segments)
        ->filter(function ($segment, $index) use ($segments) {
            $previous = $segments[$index - 1] ?? null;
            $score = abs((float) ($segment['score'] ?? 0));

            return $index === 0
                || $index === array_key_last($segments)
                || $score >= 0.35
                || ($segment['sentiment'] ?? null) !== ($previous['sentiment'] ?? null)
                || ($segment['emotion'] ?? null) !== ($previous['emotion'] ?? null)
                || ($segment['speaker'] ?? null) !== ($previous['speaker'] ?? null);
        })
        ->take(80)
        ->values();
    $emotionLegend = $eventSegments
        ->unique(fn ($segment) => $segment['emotion'] ?? $segment['emotion_label'] ?? '')
        ->take(6)
        ->values();
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
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $summary['duration_label'] ?? $timeline['duration_label'] ?? '00:00' }}</div>
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

            <div class="relative">
                <div class="absolute inset-x-0 top-0 z-0 h-32 rounded-lg bg-white/5"></div>

                <div class="relative z-10 h-32 cursor-pointer overflow-hidden rounded-lg px-2" @click="seekFromWaveform($event)">
                    <div class="pointer-events-none absolute inset-x-2 top-3 h-px bg-white/10"></div>
                    <div class="pointer-events-none absolute inset-x-2 bottom-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <template x-for="segment in segments" :key="`sentiment-${segment.id}`">
                            <span class="absolute top-0 h-full"
                                :style="`left: ${segment.left}%; width: ${segment.width}%; background-color: ${segment.color};`"></span>
                        </template>
                    </div>

                    <div class="relative flex h-full items-center gap-[3px] pb-4 pt-5">
                        @foreach($bars as $bar)
                            <button type="button" class="flex h-full flex-1 items-center justify-center rounded-sm transition hover:opacity-100"
                                title="{{ $bar['time'] ?? 0 }}"
                                @click.stop="seek({{ (float) ($bar['time'] ?? 0) }})">
                                <span class="block min-h-[4px] w-full rounded-full opacity-70 transition"
                                    :class="{ 'opacity-100': {{ (float) ($bar['time'] ?? 0) }} <= currentTime }"
                                    style="height: {{ $bar['height'] ?? 30 }}%; background-color: #64748b;"
                                    :style="`height: {{ $bar['height'] ?? 30 }}%; background-color: ${ {{ (float) ($bar['time'] ?? 0) }} <= currentTime ? '{{ $bar['color'] ?? '#64748b' }}' : '#64748b' };`">
                                </span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($eventSegments as $segment)
                        <button type="button"
                            class="absolute top-1 z-20 flex h-4 w-4 -translate-x-1/2 items-center justify-center rounded-full border border-gray-950 shadow-sm ring-1 ring-white/20 transition hover:scale-125"
                            :class="{ 'ring-2 ring-white': activeTurnId === @js($segment['turn_id'] ?? null) }"
                            style="left: {{ $segment['left'] ?? 0 }}%; background-color: {{ $segment['color'] ?? '#64748b' }};"
                            title="{{ $segment['start_label'] ?? '00:00' }} · {{ $segment['label'] ?? 'Sistema' }} · {{ $segment['emotion_label'] ?? 'Evento' }}"
                            @click.stop="seek({{ (int) ($segment['start'] ?? 0) }})">
                            <span class="h-1.5 w-1.5 rounded-full bg-white/90"></span>
                        </button>
                    @endforeach

                    <div class="absolute bottom-0 top-0 z-20 w-px bg-white shadow-[0_0_12px_rgba(255,255,255,0.75)]"
                        :style="`left: ${progress}%;`">
                        <div class="-ml-1.5 mt-1 h-3 w-3 rounded-full bg-white"></div>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-[72px_1fr] gap-2 text-xs">
                    <div class="font-semibold text-emerald-300">Agente</div>
                    <div class="relative h-5 rounded bg-white/5">
                        <template x-for="segment in segments.filter((item) => item.speaker === 'agent')" :key="segment.id">
                            <button type="button" class="absolute top-1 h-3 rounded-full bg-emerald-400/80"
                                :style="`left: ${segment.left}%; width: ${segment.width}%;`"
                                @click="seek(segment.start)"
                                :title="`${segment.start_label} · ${segment.emotion_label}`">
                            </button>
                        </template>
                    </div>
                    <div class="font-semibold text-indigo-300">Cliente</div>
                    <div class="relative h-5 rounded bg-white/5">
                        <template x-for="segment in segments.filter((item) => item.speaker === 'client')" :key="segment.id">
                            <button type="button" class="absolute top-1 h-3 rounded-full bg-indigo-400/80"
                                :style="`left: ${segment.left}%; width: ${segment.width}%;`"
                                @click="seek(segment.start)"
                                :title="`${segment.start_label} · ${segment.emotion_label}`">
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-400">
                <span class="font-semibold uppercase tracking-wide text-gray-500">Marcadores emocionales</span>
                @forelse($emotionLegend as $segment)
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $segment['color'] ?? '#64748b' }};"></span>
                        {{ $segment['emotion_label'] ?? 'Evento' }}
                    </span>
                @empty
                    <span>Sin eventos detectados</span>
                @endforelse
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
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                            {{ ucfirst($sentiment['overall'] ?? 'neutro') }}
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
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $summary['dominant_emotion_label'] ?? 'Calma' }}</span>
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
        @endif
    </div>
</div>
