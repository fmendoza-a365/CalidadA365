@php
    $audioTitle = $title ?? 'Audio';
    $audioSubtitle = $subtitle ?? null;
    $audioFileName = $fileName ?? null;
    $initialDurationLabel = $durationLabel ?? '00:00';
@endphp

<div
    x-data="{
        playing: false,
        duration: 0,
        currentTime: 0,
        speed: '1',
        bars: {{ json_encode($bars ?? null) }} || [],
        init() {
            if (this.bars.length === 0) {
                const count = 75;
                for (let i = 0; i < count; i++) {
                    const height = 15 + Math.abs(Math.sin(i * 0.15) * 45) + Math.abs(Math.cos(i * 0.35) * 20);
                    this.bars.push({
                        index: i,
                        height: Math.round(Math.min(90, height)),
                        color: '#34d399'
                    });
                }
            }
        },
        get progress() {
            return this.duration > 0 ? Math.min(100, (this.currentTime / this.duration) * 100) : 0;
        },
        get currentLabel() {
            return this.format(this.currentTime);
        },
        get durationLabel() {
            const hasNative = this.duration > 0 && Number.isFinite(this.duration);
            return hasNative ? this.format(this.duration) : '{{ $initialDurationLabel }}';
        },
        format(value) {
            if (!Number.isFinite(value) || isNaN(value)) {
                return '00:00';
            }
            const seconds = Math.max(0, Math.floor(Number(value) || 0));
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const rest = seconds % 60;

            if (hours > 0) {
                return `${hours}:${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
            }

            return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
        },
        toggle() {
            const audio = this.$refs.audio;

            if (!audio) return;

            if (audio.paused) {
                audio.play();
                this.playing = true;
            } else {
                audio.pause();
                this.playing = false;
            }
        },
        seek(seconds) {
            const audio = this.$refs.audio;

            if (!audio) return;

            const target = Math.max(0, Math.min(seconds, this.duration || audio.duration || 0));
            if (Number.isFinite(target)) {
                audio.currentTime = target;
                this.currentTime = target;
            }
        },
        seekFromBar(event) {
            const bar = event.currentTarget;
            const rect = bar.getBoundingClientRect();
            const ratio = rect.width > 0 ? (event.clientX - rect.left) / rect.width : 0;

            this.seek((this.duration || 0) * Math.max(0, Math.min(1, ratio)));
        },
        setSpeed(value) {
            const audio = this.$refs.audio;

            if (!audio) return;

            audio.playbackRate = Number(value) || 1;
        },
        onLoadedMetadata() {
            const nativeDuration = this.$refs.audio.duration;
            if (Number.isFinite(nativeDuration)) {
                this.duration = nativeDuration;
            }
            this.setSpeed(this.speed);
        },
        onTimeUpdate() {
            this.currentTime = this.$refs.audio.currentTime || 0;
            if (this.duration === 0 || !Number.isFinite(this.duration)) {
                const nativeDuration = this.$refs.audio.duration;
                if (Number.isFinite(nativeDuration)) {
                    this.duration = nativeDuration;
                }
            }
        },
        onEnded() {
            this.playing = false;
        }
    }"
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
>
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white shadow-sm dark:bg-white dark:text-gray-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-gray-900 dark:text-white">{{ $audioTitle }}</h3>
                    @if($audioSubtitle || $audioFileName)
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ $audioSubtitle ?? $audioFileName }}
                            @if($audioSubtitle && $audioFileName)
                                <span class="text-gray-400">·</span> {{ $audioFileName }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                <div class="text-[11px] font-semibold uppercase text-gray-400">Duración</div>
                <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="durationLabel">{{ $initialDurationLabel }}</div>
            </div>
        </div>
    </div>

    <div class="p-4">
        <audio x-ref="audio" preload="metadata" class="hidden"
            @loadedmetadata="onLoadedMetadata"
            @timeupdate="onTimeUpdate"
            @ended="onEnded">
            <source src="{{ $audioUrl }}">
            Tu navegador no soporta la reproducción de audio.
        </audio>

        <div class="rounded-xl bg-gray-950 p-4 text-white shadow-sm">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                        <div class="font-mono text-sm">
                            <span x-text="currentLabel">00:00</span> / <span x-text="durationLabel">{{ $initialDurationLabel }}</span>
                        </div>
                        <div class="text-xs text-gray-400">Reproducción directa de audio</div>
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

            <button type="button" class="block w-full cursor-pointer rounded-xl border border-white/10 bg-black/25 p-4 text-left"
                @click="seekFromBar($event)">
                <div class="mb-3 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <span>Línea de tiempo</span>
                    <span class="font-mono normal-case tracking-normal text-gray-300">
                        <span x-text="currentLabel">00:00</span> / <span x-text="durationLabel">{{ $initialDurationLabel }}</span>
                    </span>
                </div>
                <div class="relative h-12 flex items-center gap-[3px] overflow-hidden">
                    <template x-for="bar in bars" :key="bar.index">
                        <span class="flex h-full flex-1 items-center justify-center rounded-sm">
                            <span class="block min-h-[4px] w-full rounded-full transition-all duration-100"
                                :style="`height: ${bar.height}%; background-color: ${(duration > 0 && currentTime >= (duration * (bar.index / bars.length))) ? (bar.color || '#34d399') : '#4b5563'};`"
                                :class="(duration > 0 && currentTime >= (duration * (bar.index / bars.length))) ? 'opacity-100' : 'opacity-50'">
                            </span>
                        </span>
                    </template>
                </div>
            </button>
        </div>
    </div>
</div>
