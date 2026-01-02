<x-app-layout>
    <x-slot name="header">Reporte de Insights: {{ ucfirst($insight->type) }}</x-slot>

    <div class="space-y-6">
        {{-- Metadata & Navigation --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('insights.index') }}" class="btn-secondary btn-sm">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver
            </a>
            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ $insight->campaign->name ?? 'N/A' }}</span>
                <span>•</span>
                <span>{{ $insight->date_range_start->format('d/m/Y') }} - {{ $insight->date_range_end->format('d/m/Y') }}</span>
                <span>•</span>
                <span>Generado: {{ $insight->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        {{-- Executive Summary --}}
        <div class="card">
            <div class="card-header bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Resumen Ejecutivo</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="prose prose-indigo dark:prose-invert max-w-none">
                    {!! Str::markdown($insight->summary_content ?? $insight->key_findings['executive_summary'] ?? 'Sin resumen disponible') !!}
                </div>
            </div>
        </div>

        {{-- Improvement Opportunities --}}
        @if(!empty($insight->key_findings['improvement_opportunities']))
        <div class="card">
            <div class="card-header bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Oportunidades de Mejora</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="space-y-4">
                    @foreach($insight->key_findings['improvement_opportunities'] as $opp)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $opp['category'] }}</span>
                                    @php
                                        $priorityClass = match($opp['priority']) {
                                            'High' => 'badge-danger',
                                            'Medium' => 'badge-warning',
                                            default => 'badge-success'
                                        };
                                    @endphp
                                    <span class="badge {{ $priorityClass }}">{{ $opp['priority'] }}</span>
                                </div>
                                @if(!empty($opp['affected_count']))
                                    <span class="text-sm text-gray-500">{{ $opp['affected_count'] }} agentes</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">{{ $opp['description'] }}</p>
                            @if(!empty($opp['coaching_actions']))
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded p-3">
                                    <strong class="text-xs text-blue-700 dark:text-blue-400 block mb-1">ACCIÓN DE COACHING:</strong>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $opp['coaching_actions'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Grid: Deficiencies & Product Issues --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Deficiencies --}}
            @if(!empty($insight->key_findings['deficiencies']))
            <div class="card">
                <div class="card-header bg-gradient-to-r from-rose-50 to-transparent dark:from-rose-900/20">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Falencias Detectadas</h3>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    @foreach($insight->key_findings['deficiencies'] as $def)
                        <div class="border-l-4 border-rose-500 pl-4 py-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ $def['title'] }}</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ $def['description'] }}</p>
                            <div class="text-xs text-gray-500 space-y-1">
                                <p><strong>Frecuencia:</strong> {{ $def['frequency'] }}</p>
                                <p><strong>Causa Raíz:</strong> {{ $def['root_cause'] }}</p>
                            </div>
                            <div class="mt-2 bg-gray-50 dark:bg-gray-800 rounded p-2">
                                <strong class="text-xs text-gray-700 dark:text-gray-400">Recomendación:</strong>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ $def['recommendation'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Product Issues --}}
            @if(!empty($insight->key_findings['product_issues']))
            <div class="card">
                <div class="card-header bg-gradient-to-r from-amber-50 to-transparent dark:from-amber-900/20">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Problemas de Producto</h3>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    @foreach($insight->key_findings['product_issues'] as $issue)
                        <div class="border-l-4 border-amber-500 pl-4 py-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ $issue['issue'] }}</h4>
                            <div class="text-sm space-y-2">
                                <p class="text-rose-700 dark:text-rose-400"><strong>Impacto:</strong> {{ $issue['customer_impact'] }}</p>
                                <p class="text-gray-600 dark:text-gray-400"><strong>Evidencia:</strong> {{ $issue['evidence'] }}</p>
                                <div class="bg-green-50 dark:bg-green-900/20 rounded p-2">
                                    <strong class="text-xs text-green-700 dark:text-green-400">Solución Sugerida:</strong>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $issue['suggested_fix'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Trends --}}
        @if(!empty($insight->key_findings['trends']))
        <div class="card">
            <div class="card-header bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Análisis de Tendencias</h3>
                </div>
            </div>
            <div class="card-body">
                @php
                    $trend = $insight->key_findings['trends'];
                    $directionIcon = match($trend['overall_direction'] ?? 'stable') {
                        'improving' => '📈',
                        'declining' => '📉',
                        default => '➡️'
                    };
                @endphp
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-3xl">{{ $directionIcon }}</span>
                    <div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                            Tendencia: {{ ucfirst($trend['overall_direction'] ?? 'Estable') }}
                        </span>
                    </div>
                </div>
                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <p><strong>Observaciones:</strong> {{ $trend['key_observations'] ?? 'N/A' }}</p>
                    <p><strong>Cambios Críticos:</strong> {{ $trend['critical_changes'] ?? 'Ninguno' }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Recommendations --}}
        @if(!empty($insight->key_findings['recommendations']))
        <div class="card">
            <div class="card-header bg-gradient-to-r from-purple-50 to-transparent dark:from-purple-900/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Plan de Acción Recomendado</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="space-y-3">
                    @foreach($insight->key_findings['recommendations'] as $rec)
                        <div class="flex gap-4 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                    <span class="text-sm font-bold text-purple-700 dark:text-purple-400">{{ $rec['priority'] }}</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ $rec['action'] }}</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm mt-2">
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Impacto Esperado:</span>
                                        <p class="text-gray-800 dark:text-gray-200">{{ $rec['expected_impact'] }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Responsable:</span>
                                        <p class="text-gray-800 dark:text-gray-200">{{ $rec['responsible'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Fallback for old format (legacy findings) --}}
        @if(!empty($insight->key_findings['findings']))
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Hallazgos</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($insight->key_findings['findings'] as $finding)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $finding['title'] }}</h4>
                                <span class="badge badge-{{ $finding['impact'] === 'Alto' ? 'danger' : ($finding['impact'] === 'Medio' ? 'warning' : 'success') }}">
                                    {{ $finding['impact'] }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ $finding['description'] }}</p>
                            @if(!empty($finding['recommendation']))
                                <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded p-2 mt-2">
                                    <strong class="text-xs text-indigo-700 dark:text-indigo-400">Recomendación:</strong>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $finding['recommendation'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
