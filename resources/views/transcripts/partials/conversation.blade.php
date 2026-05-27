@php
    $turns = $turns ?? [];
    $isAudio = $isAudio ?? false;
@endphp

<div class="rounded-xl border border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-950/30">
    <div class="max-h-[34rem] overflow-y-auto px-4 py-5 sm:px-5 space-y-5 scrollbar-thin">
        @forelse($turns as $turn)
            @php
                $type = $turn['speaker'] ?? 'system';
                $label = $turn['label'] ?? 'Sistema';
                $timestamp = $turn['timestamp'] ?? null;
                $timestampSeconds = $turn['timestamp_seconds'] ?? null;
                $message = $turn['message'] ?? '';
                $turnId = $turn['id'] ?? null;
                $canSeek = $isAudio && is_numeric($timestampSeconds);
                $activeClasses = 'ring-2 ring-indigo-400/70 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-950';
            @endphp

            @if($type === 'client')
                <div @if($turnId) id="{{ $turnId }}" @endif class="flex items-start justify-end gap-3">
                    <div class="min-w-0 max-w-[84%] sm:max-w-[72%]">
                        <div class="mb-1 flex items-center justify-end gap-2 pr-1">
                            @if($timestamp)
                                <span class="font-mono text-[11px] text-gray-400">{{ $timestamp }}</span>
                            @endif
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </div>
                        <div class="rounded-2xl rounded-tr-sm bg-indigo-600 px-4 py-3 shadow-sm transition dark:bg-indigo-500 {{ $canSeek ? 'cursor-pointer hover:bg-indigo-700 dark:hover:bg-indigo-600' : '' }}"
                            @if($canSeek)
                                @click="seek({{ (int) $timestampSeconds }})"
                                :class="activeTurnId === '{{ $turnId }}' ? '{{ $activeClasses }}' : ''"
                            @endif>
                            <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-white" style="overflow-wrap: anywhere;">{{ $message }}</p>
                            @if(!empty($turn['emotion_label']))
                                <div class="mt-2 inline-flex rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-semibold text-white/90">
                                    {{ $turn['emotion_label'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                        C
                    </div>
                </div>
            @elseif($type === 'agent')
                <div @if($turnId) id="{{ $turnId }}" @endif class="flex items-start gap-3">
                    <div class="mt-5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                        A
                    </div>
                    <div class="min-w-0 max-w-[84%] sm:max-w-[72%]">
                        <div class="mb-1 flex items-center gap-2 pl-1">
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            @if($timestamp)
                                <span class="font-mono text-[11px] text-gray-400">{{ $timestamp }}</span>
                            @endif
                        </div>
                        <div class="rounded-2xl rounded-tl-sm border border-gray-200 bg-white px-4 py-3 shadow-sm transition dark:border-gray-700 dark:bg-gray-900 {{ $canSeek ? 'cursor-pointer hover:border-emerald-300 dark:hover:border-emerald-700' : '' }}"
                            @if($canSeek)
                                @click="seek({{ (int) $timestampSeconds }})"
                                :class="activeTurnId === '{{ $turnId }}' ? '{{ $activeClasses }}' : ''"
                            @endif>
                            <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-gray-800 dark:text-gray-100" style="overflow-wrap: anywhere;">{{ $message }}</p>
                            @if(!empty($turn['emotion_label']))
                                <div class="mt-2 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $turn['emotion_label'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="flex justify-center">
                    <div class="max-w-[90%] rounded-lg border border-gray-200 bg-white px-3 py-2 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</div>
                        <p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-gray-600 dark:text-gray-300" style="overflow-wrap: anywhere;">{{ $message }}</p>
                    </div>
                </div>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                No hay transcripción disponible.
            </div>
        @endforelse
    </div>
</div>
