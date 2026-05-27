<x-app-layout>
    <x-slot name="header">Bandeja Operativa</x-slot>

    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Operación de calidad</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Trabajo pendiente y alertas</h2>
                        <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                            Revisión de IA, disputas, productividad y diferencias relevantes entre IA y monitor.
                        </p>
                    </div>
                    <form method="GET" action="{{ route('work-queue.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4 xl:w-[720px]">
                        <div>
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Campaña</label>
                            <select name="campaign_id" class="form-select">
                                <option value="">Todas</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ (string) $filters['campaign_id'] === (string) $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary btn-md w-full">Actualizar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Revisión monitor</div>
                        <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                    </div>
                    <div class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $counts['pending_review'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Evaluaciones que requieren criterio humano</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cola IA / fallos</div>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    </div>
                    <div class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $counts['ai_queue'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pendientes, procesando o fallidas</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disputas abiertas</div>
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                    </div>
                    <div class="mt-3 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ $counts['disputes'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Casos pendientes de resolución</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Delta IA alto</div>
                        <span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span>
                    </div>
                    <div class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $counts['calibration_alerts'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Diferencias de 10 puntos o más</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-3">
            <div class="card 2xl:col-span-2">
                <div class="card-header">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Productividad Operativa</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $filters['start_date'] }} - {{ $filters['end_date'] }}</span>
                    </div>
                </div>
                <div class="card-body space-y-5">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Horas a publicar</div>
                            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($productivity['avg_hours_to_publish'], 2) }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Horas disputa</div>
                            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($productivity['avg_hours_to_resolve_dispute'], 2) }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Manual</div>
                            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $productivity['manual_evaluations'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">IA</div>
                            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $productivity['ai_evaluations'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Cerradas</div>
                            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $productivity['closed_evaluations'] }}</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-4 py-3 text-left">Monitor</th>
                                    <th class="px-4 py-3 text-center">Evaluaciones</th>
                                    <th class="px-4 py-3 text-center">Promedio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($productivity['monitor_rows'] as $monitor)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $monitor['name'] }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $monitor['count'] }}</td>
                                        <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">{{ number_format($monitor['avg_score'], 1) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Sin monitores con evaluaciones en el período.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Alertas de calibración IA</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        @forelse($calibrationAlerts as $pair)
                            <a href="{{ route('evaluations.show', $pair['manual_evaluation_id']) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900/60">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $pair['agent'] }}</div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $pair['campaign'] }}</div>
                                    </div>
                                    <span class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ $pair['score_delta'] > 0 ? '+' : '' }}{{ number_format($pair['score_delta'], 1) }} pp</span>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-200 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No hay diferencias altas IA vs monitor.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Evaluaciones IA pendientes de revisión</h3>
                </div>
                <div class="card-body">
                    @include('work-queue.partials.evaluation-list', ['items' => $pendingReview, 'empty' => 'No hay evaluaciones pendientes de revisión.'])
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Disputas por resolver</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        @forelse($disputes as $dispute)
                            <a href="{{ route('evaluations.show', $dispute->evaluation) }}" class="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-rose-200 hover:bg-rose-50/30 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-rose-500/30 dark:hover:bg-rose-500/5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $dispute->evaluation->agent?->name ?? 'N/A' }}</div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $dispute->evaluation->campaign?->name ?? 'N/A' }}</div>
                                        <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ $dispute->created_at->diffForHumans() }}</div>
                                    </div>
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                                        {{ \App\Models\DisputeResolution::statusLabel($dispute->status) }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-200 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No hay disputas abiertas.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Cola IA y fallos</h3>
            </div>
            <div class="card-body">
                @include('work-queue.partials.evaluation-list', ['items' => $aiQueue, 'empty' => 'No hay evaluaciones IA en cola o con fallo.'])
            </div>
        </div>
    </div>
</x-app-layout>
