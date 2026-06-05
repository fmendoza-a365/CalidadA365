<x-app-layout>
    <x-slot name="header">Rendimiento IA</x-slot>

    <div class="space-y-6">
        <div class="card">
            <div class="card-body">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Gobierno de IA</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Rendimiento por proveedor, modelo y prompt</h2>
                        <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                            Controla volumen, fallos, publicación y score promedio para decidir qué modelo usar en operación.
                        </p>
                    </div>
                    <form method="GET" action="{{ route('settings.ai.performance') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4 xl:w-[720px]">
                        <div>
                            <label class="form-label">Desde</label>
                            <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Hasta</label>
                            <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Campaña / Subcampaña</label>
                            <select name="campaign_id" class="form-select">
                                <option value="">Todas</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ (string) $filters['campaign_id'] === (string) $campaign->id ? 'selected' : '' }}>{{ $campaign->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary btn-md w-full">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Evaluaciones IA</div>
                    <div class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $performance['total_ai_evaluations'] }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fallidas</div>
                    <div class="mt-3 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ $performance['failed_ai_evaluations'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($performance['failed_rate'], 1) }}% del total</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Publicadas</div>
                    <div class="mt-3 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $performance['published_ai_evaluations'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($performance['published_rate'], 1) }}% operativo</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Puntaje promedio</div>
                    <div class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($performance['average_score'], 2) }}%</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Período</div>
                    <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $filters['start_date'] }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $filters['end_date'] }}</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            @foreach([
                'provider_rows' => 'Proveedor',
                'model_rows' => 'Modelo',
                'prompt_rows' => 'Prompt',
            ] as $key => $title)
                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="space-y-4">
                            @forelse($performance[$key] as $row)
                                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $row['label'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['count'] }} evaluaciones · {{ $row['failed'] }} fallos</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900 dark:text-white">{{ number_format($row['avg_score'], 1) }}%</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">prom.</div>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <div class="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                            <span>Fallo</span>
                                            <span>{{ number_format($row['failed_rate'], 1) }}%</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-full rounded-full {{ $row['failed_rate'] > 5 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, max(0, $row['failed_rate'])) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Sin datos para este período.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
