@php
    $statusTone = function (string $status): string {
        return match ($status) {
            \App\Models\Evaluation::STATUS_PENDING_AI,
            \App\Models\Evaluation::STATUS_AI_PROCESSING,
            \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            \App\Models\Evaluation::STATUS_PENDING_MONITOR_REVIEW => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20',
            \App\Models\Evaluation::STATUS_AI_FAILED,
            \App\Models\Evaluation::STATUS_AGENT_DISPUTED => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20',
            \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
            default => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
        };
    };

    $scoreTone = function ($score): string {
        if ($score === null) {
            return 'text-gray-400 dark:text-gray-500';
        }

        return match (true) {
            (float) $score >= 90 => 'text-emerald-600 dark:text-emerald-400',
            (float) $score >= 80 => 'text-sky-600 dark:text-sky-400',
            (float) $score >= 70 => 'text-amber-600 dark:text-amber-400',
            default => 'text-rose-600 dark:text-rose-400',
        };
    };
@endphp

<div class="space-y-3">
    @forelse($items as $evaluation)
        <a href="{{ route('evaluations.show', $evaluation) }}"
            class="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-500/30 dark:hover:bg-indigo-500/5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $evaluation->agent?->name ?? 'Sin asesor' }}</span>
                        <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $statusTone($evaluation->status) }}">
                            {{ \App\Models\Evaluation::statusLabel($evaluation->status) }}
                        </span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $evaluation->campaign?->name ?? 'Sin campaña' }}</div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                        <span>{{ $evaluation->type === 'ai' ? 'Evaluación IA' : 'Evaluación manual' }}</span>
                        <span>·</span>
                        <span>{{ $evaluation->created_at->diffForHumans() }}</span>
                        @if($evaluation->interaction?->occurred_at)
                            <span>·</span>
                            <span>Interacción {{ $evaluation->interaction->occurred_at->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex-shrink-0 text-right">
                    <div class="text-xl font-bold {{ $scoreTone($evaluation->percentage_score) }}">
                        {{ $evaluation->percentage_score !== null ? number_format((float) $evaluation->percentage_score, 1).'%' : '--' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">Ver detalle</div>
                </div>
            </div>
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
            {{ $empty }}
        </div>
    @endforelse
</div>
