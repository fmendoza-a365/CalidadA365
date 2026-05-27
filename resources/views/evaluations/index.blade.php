<x-app-layout>
    <x-slot name="header">Evaluaciones</x-slot>

    @php
        $statusTone = function (string $status): string {
            return match ($status) {
                \App\Models\Evaluation::STATUS_PENDING_AI,
                \App\Models\Evaluation::STATUS_AI_PROCESSING,
                \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
                \App\Models\Evaluation::STATUS_PENDING_MONITOR_REVIEW => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20',
                \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
                \App\Models\Evaluation::STATUS_AGENT_ACCEPTED,
                \App\Models\Evaluation::STATUS_DISPUTE_RESOLVED => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
                \App\Models\Evaluation::STATUS_AGENT_DISPUTED,
                \App\Models\Evaluation::STATUS_AI_FAILED => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20',
                \App\Models\Evaluation::STATUS_CLOSED => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
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

        $scoreBar = function ($score): string {
            if ($score === null) {
                return 'bg-gray-300 dark:bg-gray-700';
            }

            return match (true) {
                (float) $score >= 90 => 'bg-emerald-500',
                (float) $score >= 80 => 'bg-sky-500',
                (float) $score >= 70 => 'bg-amber-500',
                default => 'bg-rose-500',
            };
        };

        $typeTone = fn (string $type): string => $type === 'ai'
            ? 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/20'
            : 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-500/10 dark:text-cyan-300 dark:border-cyan-500/20';

        $actionLabel = function (\App\Models\Evaluation $evaluation): string {
            return match ($evaluation->status) {
                \App\Models\Evaluation::STATUS_PENDING_AI,
                \App\Models\Evaluation::STATUS_AI_PROCESSING => 'Ver cola',
                \App\Models\Evaluation::STATUS_AI_FAILED => 'Revisar fallo',
                \App\Models\Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED => 'Revisar',
                \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'Seguimiento',
                \App\Models\Evaluation::STATUS_AGENT_DISPUTED => 'Resolver',
                \App\Models\Evaluation::STATUS_CLOSED => 'Auditar',
                default => 'Ver detalle',
            };
        };

        $activeFilterCount = collect(request()->only([
            'q',
            'campaign_id',
            'status',
            'type',
            'score_band',
            'start_date',
            'end_date',
        ]))->filter(fn ($value) => filled($value))->count();
    @endphp

    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
            @foreach([
                ['label' => 'Total', 'value' => $summary['total'], 'tone' => 'text-gray-900 dark:text-white'],
                ['label' => 'Promedio', 'value' => number_format($summary['avg_score'], 1).'%', 'tone' => 'text-gray-900 dark:text-white'],
                ['label' => 'Por revisar', 'value' => $summary['pending_review'], 'tone' => 'text-indigo-600 dark:text-indigo-400'],
                ['label' => 'Disputas', 'value' => $summary['disputed'], 'tone' => 'text-rose-600 dark:text-rose-400'],
                ['label' => 'Críticas', 'value' => $summary['critical'], 'tone' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Cerradas', 'value' => $summary['closed'], 'tone' => 'text-gray-900 dark:text-white'],
            ] as $metric)
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-[#141414]">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</div>
                    <div class="mt-1 text-2xl font-semibold {{ $metric['tone'] }}">{{ $metric['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Centro operativo de evaluaciones</h3>
                            @if($activeFilterCount > 0)
                                <span class="badge badge-neutral">{{ $activeFilterCount }} filtro(s)</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $evaluations->total() }} registros encontrados</p>
                    </div>
                    <a href="{{ route('exports.evaluations', request()->query()) }}" class="btn-secondary btn-sm w-fit">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar
                    </a>
                </div>
            </div>

            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <form method="GET" action="{{ route('evaluations.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1.2fr)_repeat(4,minmax(130px,1fr))_minmax(250px,1fr)_auto]">
                    <div class="md:col-span-2 xl:col-span-1">
                        <label for="q" class="form-label">Buscar</label>
                        <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-input" placeholder="Agente, campaña o monitor">
                    </div>

                    <div>
                        <label for="campaign_id" class="form-label">Campaña</label>
                        <select name="campaign_id" id="campaign_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($campaigns ?? [] as $campaign)
                                <option value="{{ $campaign->id }}" {{ request('campaign_id') == $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="form-label">Estado</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ \App\Models\Evaluation::statusLabel($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="type" class="form-label">Tipo</label>
                        <select name="type" id="type" class="form-select">
                            <option value="">Todos</option>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="score_band" class="form-label">Score</label>
                        <select name="score_band" id="score_band" class="form-select">
                            <option value="">Todos</option>
                            <option value="excellent" {{ request('score_band') === 'excellent' ? 'selected' : '' }}>90%+</option>
                            <option value="good" {{ request('score_band') === 'good' ? 'selected' : '' }}>80% - 89%</option>
                            <option value="watch" {{ request('score_band') === 'watch' ? 'selected' : '' }}>70% - 79%</option>
                            <option value="critical" {{ request('score_band') === 'critical' ? 'selected' : '' }}>&lt; 70%</option>
                            <option value="unscored" {{ request('score_band') === 'unscored' ? 'selected' : '' }}>Sin score</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:col-span-2 xl:col-span-1">
                        <div>
                            <label for="start_date" class="form-label">Desde</label>
                            <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" class="form-input">
                        </div>
                        <div>
                            <label for="end_date" class="form-label">Hasta</label>
                            <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" class="form-input">
                        </div>
                    </div>

                    <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                        <button type="submit" class="btn-primary btn-md">Filtrar</button>
                        <a href="{{ route('evaluations.index') }}" class="btn-secondary btn-md">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="hidden overflow-x-auto xl:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Evaluación</th>
                            <th class="w-44">Score</th>
                            <th class="w-56">Estado</th>
                            <th class="w-56">Responsable</th>
                            <th class="w-32 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluations as $evaluation)
                            @php
                                $score = $evaluation->percentage_score;
                                $hasScore = $score !== null;
                                $scoreWidth = $hasScore ? max(0, min(100, (float) $score)) : 0;
                            @endphp
                            <tr>
                                <td class="wrap-text">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            #{{ $evaluation->id }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $evaluation->agent?->name ?? 'Sin asesor' }}</span>
                                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $typeTone($evaluation->type) }}">
                                                    {{ $evaluation->type === 'ai' ? 'IA' : 'Manual' }}
                                                </span>
                                                @if($evaluation->is_gold)
                                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">Gold</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $evaluation->campaign?->name ?? 'Sin campaña' }}</div>
                                            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                {{ $evaluation->interaction?->occurred_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                                @if($evaluation->ai_provider)
                                                    · {{ strtoupper($evaluation->ai_provider) }} {{ $evaluation->ai_model ? '/ '.$evaluation->ai_model : '' }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="w-36">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-lg font-semibold {{ $scoreTone($score) }}">{{ $hasScore ? number_format((float) $score, 1).'%' : '--' }}</span>
                                            <span class="text-xs text-gray-400">{{ $evaluation->total_score !== null ? number_format((float) $evaluation->total_score, 0) : '--' }}/{{ number_format((float) $evaluation->max_possible_score, 0) }}</span>
                                        </div>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-full rounded-full {{ $scoreBar($score) }}" style="width: {{ $scoreWidth }}%"></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone($evaluation->status) }}">
                                        {{ \App\Models\Evaluation::statusLabel($evaluation->status) }}
                                    </span>
                                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $evaluation->created_at->diffForHumans() }}</div>
                                </td>

                                <td>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $evaluation->evaluator?->name ?? $evaluation->reviewer?->name ?? 'Pendiente' }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $evaluation->visible_to_agent_at ? 'Publicado '.$evaluation->visible_to_agent_at->format('d/m/Y') : 'Sin publicación' }}
                                    </div>
                                </td>

                                <td class="text-right">
                                    <a href="{{ route('evaluations.show', $evaluation) }}" class="btn-secondary btn-sm">
                                        {{ $actionLabel($evaluation) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state py-12">
                                        <div class="empty-state-icon">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="font-medium text-gray-900 dark:text-white">No hay evaluaciones con estos filtros.</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta la búsqueda o limpia los filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 p-4 xl:hidden">
                @forelse($evaluations as $evaluation)
                    @php
                        $score = $evaluation->percentage_score;
                        $hasScore = $score !== null;
                        $scoreWidth = $hasScore ? max(0, min(100, (float) $score)) : 0;
                    @endphp
                    <a href="{{ route('evaluations.show', $evaluation) }}" class="block rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $evaluation->agent?->name ?? 'Sin asesor' }}</div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $evaluation->campaign?->name ?? 'Sin campaña' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-semibold {{ $scoreTone($score) }}">{{ $hasScore ? number_format((float) $score, 1).'%' : '--' }}</div>
                                <div class="text-xs text-gray-400">{{ $evaluation->type === 'ai' ? 'IA' : 'Manual' }}</div>
                            </div>
                        </div>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full rounded-full {{ $scoreBar($score) }}" style="width: {{ $scoreWidth }}%"></div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone($evaluation->status) }}">
                                {{ \App\Models\Evaluation::statusLabel($evaluation->status) }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $evaluation->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-gray-900">
                        <p class="font-medium text-gray-900 dark:text-white">No hay evaluaciones con estos filtros.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta la búsqueda o limpia los filtros.</p>
                    </div>
                @endforelse
            </div>

            @if($evaluations->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $evaluations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
