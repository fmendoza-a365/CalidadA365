<x-app-layout>
    <x-slot name="header">Insights & Análisis de Calidad</x-slot>

    <div class="space-y-6">
        {{-- Campaign Filter --}}
        @php
            $parentCampaigns = $campaigns->whereNull('parent_id');
            $subcampaignsByParent = $campaigns->whereNotNull('parent_id')->groupBy('parent_id');
        @endphp
        <div class="card">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('insights.index') }}" class="flex flex-wrap items-end gap-3" x-data="{
                    parentCampaignId: '{{ request('parent_campaign_id') }}',
                    campaignId: '{{ request('campaign_id') }}',
                    subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                    get availableSubcampaigns() {
                        return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                    }
                }">
                    <div>
                        <label for="filter_parent_campaign" class="form-label">Campaña</label>
                        <select name="parent_campaign_id" id="filter_parent_campaign" x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                            <option value="">Todas</option>
                            @foreach($parentCampaigns as $pCamp)
                                <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter_campaign" class="form-label">Subcampaña</label>
                        <select name="campaign_id" id="filter_campaign" x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                            <option value="">Todas</option>
                            <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary btn-md">Filtrar</button>
                        @if(request('campaign_id') || request('parent_campaign_id'))
                            <a href="{{ route('insights.index') }}" class="btn-secondary btn-md">Limpiar</a>
                        @endif
                    </div>
                    @if($campaign)
                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Mostrando datos de: <strong class="text-gray-700 dark:text-gray-300">{{ $campaign->displayName() }}</strong>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- Overview Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Evaluations --}}
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Evaluaciones Totales</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($stats['total_evaluations']) }}</p>
                        </div>
                        <div class="p-3 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Average Score --}}
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Promedio General</p>
                            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-2">{{ number_format($stats['avg_score'], 1) }}%</p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Compliance Rate --}}
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tasa de Cumplimiento</p>
                            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">{{ number_format($stats['compliance_rate'], 1) }}%</p>
                            <p class="text-xs text-gray-500 mt-1">≥ 80% score</p>
                        </div>
                        <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Critical Failures --}}
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fallas Críticas</p>
                            <p class="text-3xl font-bold text-rose-600 dark:text-rose-400 mt-2">{{ number_format($stats['critical_failures']) }}</p>
                        </div>
                        <div class="p-3 bg-rose-100 dark:bg-rose-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Trends & Performance Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Score Trend --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Tendencia (30 días)</h3>
                </div>
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            @if($stats['trend_direction'] === 'up')
                                <span class="inline-flex items-center text-emerald-600 dark:text-emerald-400">
                                    <svg class="w-5 h-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span class="font-semibold">Mejorando</span>
                                </span>
                            @elseif($stats['trend_direction'] === 'down')
                                <span class="inline-flex items-center text-rose-600 dark:text-rose-400">
                                    <svg class="w-5 h-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                    </svg>
                                    <span class="font-semibold">Declinando</span>
                                </span>
                            @else
                                <span class="inline-flex items-center text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold">Estable</span>
                                </span>
                            @endif
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($stats['trend_change'], 1) }}%</p>
                            <p class="text-xs text-gray-500 mt-1">vs período anterior</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top Performers --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Top Performers</h3>
                </div>
                <div class="card-body">
                    <ul class="space-y-2">
                        @forelse($stats['top_performers'] as $performer)
                            <li class="flex items-center justify-between">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $performer->agent->full_name }}</span>
                                <span class="badge badge-success">{{ number_format($performer->avg_score, 0) }}%</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">Sin datos suficientes</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Bottom Performers --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Necesitan Apoyo</h3>
                </div>
                <div class="card-body">
                    <ul class="space-y-2">
                        @forelse($stats['bottom_performers'] as $performer)
                            <li class="flex items-center justify-between">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $performer->agent->full_name }}</span>
                                <span class="badge badge-danger">{{ number_format($performer->avg_score, 0) }}%</span>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">Sin datos suficientes</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Top Failed Criteria --}}
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Top 5 Fallas Recurrentes</h3>
            </div>
            <div class="card-body">
                <ul class="space-y-3">
                    @foreach($stats['top_failed_criteria'] as $index => $criteria)
                        <li class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-400">{{ $index + 1 }}</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $criteria->name }}</span>
                            </div>
                            <span class="badge badge-danger flex-shrink-0 ml-2">{{ $criteria->count }} veces</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- AI Report Generator --}}
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Generar reporte ejecutivo con IA</h3>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">El reporte se arma con evaluaciones reales del periodo seleccionado y queda listo para presentar.</p>
            </div>
            <div class="card-body">
                @php
                    $parentCampaigns = $campaigns->whereNull('parent_id');
                    $subcampaignsByParent = $campaigns->whereNotNull('parent_id')->groupBy('parent_id');
                @endphp
                <form action="{{ route('insights.generate') }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end" x-data="{
                    parentCampaignId: '',
                    campaignId: '',
                    subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                    get availableSubcampaigns() {
                        return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                    }
                }">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Campaña</label>
                        <select name="parent_campaign_id" x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                            <option value="">Todas las campañas visibles</option>
                            @foreach($parentCampaigns as $pCamp)
                                <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subcampaña</label>
                        <select name="campaign_id" x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                            <option value="">Todas las subcampañas</option>
                            <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                <option :value="sub.id" x-text="sub.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Enfoque</label>
                        <select name="type" class="form-select" required>
                            <option value="operational">Operaciones</option>
                            <option value="strategic">Cliente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Periodo</label>
                        <select name="days" class="form-select" required>
                            <option value="7">Últimos 7 días</option>
                            <option value="30">Últimos 30 días</option>
                            <option value="90">Últimos 3 meses</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary w-full">
                            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Generar reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reports History --}}
        <div class="card">
            <div class="card-header">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Historial de Reportes</h3>
                    <span class="badge badge-neutral">{{ $reports->total() }}</span>
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Campaña / Subcampaña</th>
                            <th>Tipo</th>
                            <th>Periodo Analizado</th>
                            <th>Generado Por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td class="whitespace-nowrap">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $report->campaign?->displayName() ?? 'N/A' }}</td>
                                <td>
                                    @if($report->type === 'operational')
                                        <span class="badge badge-primary">Operacional</span>
                                    @elseif($report->type === 'strategic')
                                        <span class="badge badge-success">Estratégico</span>
                                    @else
                                        <span class="badge badge-neutral">Combinado</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">{{ $report->date_range_start->format('d/m') }} - {{ $report->date_range_end->format('d/m') }}</td>
                                <td>{{ $report->creator->full_name ?? 'Sistema' }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('insights.show', $report) }}" class="btn-secondary btn-sm">Ver Reporte</a>
                                        <form action="{{ route('insights.destroy', $report) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este reporte?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-sm text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8">
                                    <div class="text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p>No hay reportes generados aún.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($reports->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
