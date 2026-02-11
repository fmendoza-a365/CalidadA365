<x-app-layout>
    <x-slot name="header">Dashboard Supervisor</x-slot>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stat-card stat-card-indigo card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Mi Equipo</span>
                <div class="stat-icon bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $agentPerformance->count() }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asesores asignados</p>
        </div>

        <div class="stat-card stat-card-emerald card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Promedio Equipo</span>
                <div class="stat-icon bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ number_format($teamAverage, 1) }}%</div>
            <div class="progress mt-3">
                <div class="progress-bar bg-emerald-500" style="width: {{ $teamAverage }}%"></div>
            </div>
        </div>

        <div class="stat-card stat-card-blue card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Evaluaciones Mes</span>
                <div class="stat-icon bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $agentPerformance->sum('evaluations_count') }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total realizadas</p>
        </div>
    </div>

    <!-- Team Performance Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white">Desempeño del Equipo</h3>
            <a href="{{ route('evaluations.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                Ver evaluaciones →
            </a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th class="text-center">Evaluaciones</th>
                        <th class="text-center">Promedio</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agentPerformance as $stat)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="avatar avatar-md bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                                        {{ substr($stat->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $stat->name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-neutral">{{ $stat->evaluations_count }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    $score = floatval($stat->avg_score);
                                    $scoreClass = match(true) {
                                        $score >= 90 => 'score-excellent',
                                        $score >= 80 => 'score-good',
                                        $score >= 70 => 'score-average',
                                        default => 'score-poor',
                                    };
                                @endphp
                                <span class="text-lg font-bold {{ $scoreClass }}">{{ number_format($score, 1) }}%</span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('evaluations.index', ['agent_id' => $stat->id]) }}" class="btn-ghost text-sm">
                                    Ver detalles
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state py-8">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400">No hay asesores asignados</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
