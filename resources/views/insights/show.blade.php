<x-app-layout>
    @php
        $findings = $findings ?? ($insight->key_findings ?? []);
        $snapshot = $snapshot ?? data_get($findings, 'report_snapshot', []);
        $scope = data_get($snapshot, 'scope', []);
        $metrics = data_get($snapshot, 'metrics', []);
        $scoreDistribution = collect(data_get($snapshot, 'score_distribution', []));
        $topFailedCriteria = collect(data_get($snapshot, 'top_failed_criteria', []));
        $agentPerformance = collect(data_get($snapshot, 'agent_performance', []));
        $campaignBreakdown = collect(data_get($snapshot, 'campaign_breakdown', []));
        $slides = collect(data_get($findings, 'presentation_slides', []));
        $maxBandCount = max(1, (int) $scoreDistribution->max('count'));
        $renderMarkdown = fn ($value) => \Illuminate\Support\Str::markdown((string) $value, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $typeLabel = [
            'operational' => 'Operaciones',
            'strategic' => 'Cliente',
            'combined' => 'Combinado',
        ][$insight->type] ?? ucfirst($insight->type);
        $scoreTone = function ($score) {
            $score = (float) $score;

            return $score >= 90
                ? 'text-emerald-600 dark:text-emerald-300'
                : ($score >= 80
                    ? 'text-sky-600 dark:text-sky-300'
                    : ($score >= 70
                        ? 'text-amber-600 dark:text-amber-300'
                        : 'text-rose-600 dark:text-rose-300'));
        };
        $priorityTone = function ($priority) {
            $priority = strtolower((string) $priority);

            return in_array($priority, ['high', 'alta', '1'], true)
                ? 'badge-danger'
                : (in_array($priority, ['medium', 'media', '2'], true) ? 'badge-warning' : 'badge-success');
        };
    @endphp

    <x-slot name="header">Reporte de Insights</x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success no-print">{{ session('success') }}</div>
        @endif

        <div class="no-print flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <a href="{{ route('insights.index') }}" class="btn-secondary btn-sm w-fit">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver
            </a>
            <button type="button" class="btn-primary btn-sm w-fit" onclick="window.print()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0h12v4H8v-4z" />
                </svg>
                Imprimir / guardar PDF
            </button>
        </div>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-6 dark:border-gray-800 dark:bg-gray-950">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-primary">{{ $typeLabel }}</span>
                            <span class="badge badge-neutral">{{ data_get($scope, 'campaign_name', $insight->campaign->name ?? 'Todas las campañas visibles') }}</span>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Informe ejecutivo de calidad</h1>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $insight->date_range_start->format('d/m/Y') }} al {{ $insight->date_range_end->format('d/m/Y') }}
                            · Generado por {{ $insight->creator->name ?? 'Sistema' }}
                            · {{ $insight->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:w-[520px]">
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Evaluaciones</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format((int) data_get($metrics, 'total_evaluations', 0)) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Promedio</div>
                            <div class="mt-1 text-2xl font-semibold {{ $scoreTone(data_get($metrics, 'average_score', 0)) }}">{{ number_format((float) data_get($metrics, 'average_score', 0), 1) }}%</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Cumplimiento</div>
                            <div class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-300">{{ number_format((float) data_get($metrics, 'compliance_rate', 0), 1) }}%</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Críticas</div>
                            <div class="mt-1 text-2xl font-semibold text-rose-600 dark:text-rose-300">{{ number_format((int) data_get($metrics, 'critical_failures', 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-8 p-6">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                    <div class="space-y-6">
                        <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Resumen ejecutivo</h2>
                            <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                                {!! $renderMarkdown($insight->summary_content ?? data_get($findings, 'executive_summary', 'Sin resumen disponible.')) !!}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lectura para operaciones</h2>
                                <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                                    {!! $renderMarkdown(data_get($findings, 'operations_summary', 'Sin resumen operativo disponible.')) !!}
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lectura para cliente</h2>
                                <div class="prose prose-sm mt-3 max-w-none dark:prose-invert">
                                    {!! $renderMarkdown(data_get($findings, 'client_summary', 'Sin resumen para cliente disponible.')) !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                            <h2 class="font-semibold text-gray-900 dark:text-white">Distribución de puntajes</h2>
                            <div class="mt-4 space-y-3">
                                @forelse($scoreDistribution as $band)
                                    @php($width = round(((int) data_get($band, 'count', 0) / $maxBandCount) * 100))
                                    <div>
                                        <div class="mb-1 flex items-center justify-between text-sm">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ data_get($band, 'label') }}</span>
                                            <span class="text-gray-500 dark:text-gray-400">{{ number_format((int) data_get($band, 'count', 0)) }}</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $width }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin puntajes disponibles.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                            <h2 class="font-semibold text-gray-900 dark:text-white">Desempeño a revisar</h2>
                            <div class="mt-4 space-y-3">
                                @forelse($agentPerformance as $agent)
                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ data_get($agent, 'agent') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ data_get($agent, 'evaluations') }} eval. · {{ data_get($agent, 'critical_failures') }} críticas</div>
                                        </div>
                                        <div class="text-sm font-semibold {{ $scoreTone(data_get($agent, 'average_score', 0)) }}">{{ number_format((float) data_get($agent, 'average_score', 0), 1) }}%</div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin datos por asesor.</p>
                                @endforelse
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Criterios con mayor recurrencia</h2>
                        <div class="mt-4 space-y-4">
                            @forelse($topFailedCriteria as $criteria)
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ data_get($criteria, 'criteria') }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ data_get($criteria, 'category') }}</div>
                                        </div>
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="badge {{ data_get($criteria, 'critical') ? 'badge-danger' : 'badge-warning' }}">{{ number_format((int) data_get($criteria, 'count', 0)) }} casos</span>
                                            @if(data_get($criteria, 'critical'))
                                                <span class="text-xs font-semibold text-rose-600 dark:text-rose-300">Crítico</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if(!empty(data_get($criteria, 'examples', [])))
                                        <div class="mt-3 space-y-2">
                                            @foreach(data_get($criteria, 'examples', []) as $example)
                                                <p class="rounded border-l-2 border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ $example }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">No hay criterios fallidos en el periodo.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Oportunidades detectadas por IA</h2>
                        <div class="mt-4 space-y-4">
                            @forelse(data_get($findings, 'improvement_opportunities', []) as $opportunity)
                                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ data_get($opportunity, 'category') }}</h3>
                                        <span class="badge {{ $priorityTone(data_get($opportunity, 'priority')) }}">{{ data_get($opportunity, 'priority', 'Prioridad') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ data_get($opportunity, 'description') }}</p>
                                    @if(data_get($opportunity, 'coaching_actions'))
                                        <p class="mt-3 rounded border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">{{ data_get($opportunity, 'coaching_actions') }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">Sin oportunidades generadas.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Plan de acción</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        @forelse(data_get($findings, 'recommendations', []) as $recommendation)
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-200">
                                    {{ data_get($recommendation, 'priority', $loop->iteration) }}
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ data_get($recommendation, 'action') }}</h3>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ data_get($recommendation, 'expected_impact') }}</p>
                                <div class="mt-3 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ data_get($recommendation, 'responsible') }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Sin recomendaciones generadas.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Guion para reunión</h2>
                        <span class="badge badge-neutral">{{ $slides->count() }} slides sugeridos</span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @forelse($slides as $slide)
                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ data_get($slide, 'title') }}</h3>
                                <ul class="mt-3 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                    @foreach(data_get($slide, 'bullets', []) as $bullet)
                                        <li class="flex gap-2">
                                            <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span>
                                            <span>{{ $bullet }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if(data_get($slide, 'speaker_note'))
                                    <p class="mt-3 rounded border border-gray-200 bg-white px-3 py-2 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">{{ data_get($slide, 'speaker_note') }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Sin guion generado.</p>
                        @endforelse
                    </div>
                </div>

                @if($campaignBreakdown->count() > 1)
                    <div class="rounded-lg border border-gray-200 p-5 dark:border-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Detalle por campaña</h2>
                        <div class="mt-4 overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Campaña</th>
                                        <th>Evaluaciones</th>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaignBreakdown as $row)
                                        <tr>
                                            <td>{{ data_get($row, 'campaign') }}</td>
                                            <td>{{ number_format((int) data_get($row, 'evaluations', 0)) }}</td>
                                            <td class="{{ $scoreTone(data_get($row, 'average_score', 0)) }}">{{ number_format((float) data_get($row, 'average_score', 0), 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <style>
        @media print {
            .no-print,
            aside.fixed,
            nav {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            main {
                padding: 0 !important;
            }

            .card,
            section {
                box-shadow: none !important;
            }
        }
    </style>
</x-app-layout>
