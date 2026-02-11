<x-app-layout>
    <x-slot name="header">Mi Desempeño</x-slot>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card stat-card-indigo card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Evaluaciones</span>
                <div class="stat-icon bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['total_evaluations'] }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total recibidas</p>
        </div>

        <div class="stat-card stat-card-emerald card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Mi Promedio</span>
                <div class="stat-icon bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['avg_score'] }}%</div>
            <div class="progress mt-3">
                <div class="progress-bar bg-emerald-500" style="width: {{ $stats['avg_score'] }}%"></div>
            </div>
        </div>

        <div class="stat-card stat-card-amber card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Pendientes</span>
                <div class="stat-icon bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $pendingResponses }}</div>
            @if($pendingResponses > 0)
                <p class="text-sm text-amber-600 dark:text-amber-400 mt-1 font-medium">Requieren tu firma</p>
            @else
                <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">Estás al día</p>
            @endif
        </div>

        <div class="stat-card stat-card-rose card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Últimos 7 Días</span>
                <div class="stat-icon bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $recentEvaluations->count() }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Evaluaciones nuevas</p>
        </div>
    </div>

    <!-- Recent Evaluations Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white">Mis Evaluaciones Recientes</h3>
            <a href="{{ route('evaluations.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                Ver todas →
            </a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Campaña</th>
                        <th class="text-center">Puntaje</th>
                        <th class="text-center">Estado</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentEvaluations as $eval)
                        <tr>
                            <td class="text-gray-600 dark:text-gray-400">
                                {{ $eval->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $eval->campaign->name }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    $score = $eval->percentage_score;
                                    $scoreClass = match(true) {
                                        $score >= 90 => 'score-excellent',
                                        $score >= 80 => 'score-good',
                                        $score >= 70 => 'score-average',
                                        default => 'score-poor',
                                    };
                                @endphp
                                <span class="text-lg font-bold {{ $scoreClass }}">{{ number_format($score, 0) }}%</span>
                            </td>
                            <td class="text-center">
                                @if($eval->status === 'visible_to_agent')
                                    <span class="badge badge-warning">Pendiente Firma</span>
                                @elseif($eval->status === 'agent_responded')
                                    <span class="badge badge-success">Firmada</span>
                                @elseif($eval->status === 'disputed')
                                    <span class="badge badge-danger">En Disputa</span>
                                @else
                                    <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $eval->status)) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('evaluations.show', $eval) }}" class="btn-secondary py-1.5 px-3 text-sm">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-8">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400">No tienes evaluaciones recientes</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
