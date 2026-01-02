<x-app-layout>
    <x-slot name="header">Dashboard Administrativo</x-slot>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card stat-card-indigo card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Total Evaluaciones</span>
                <div class="stat-icon bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ number_format($stats['total_evaluations']) }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">En todas las campañas</p>
        </div>

        <div class="stat-card stat-card-emerald card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Promedio General</span>
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
                <span class="stat-label">Disputas Pendientes</span>
                <div class="stat-icon bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['pending_disputes'] }}</div>
            @if($stats['pending_disputes'] > 0)
                <p class="text-sm text-amber-600 dark:text-amber-400 mt-1 font-medium">Requieren atención</p>
            @else
                <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">Todo en orden</p>
            @endif
        </div>

        <div class="stat-card stat-card-blue card-hover">
            <div class="flex items-center justify-between mb-4">
                <span class="stat-label">Campañas Activas</span>
                <div class="stat-icon bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['campaigns_active'] }}</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">En operación</p>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Evaluations -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Evaluaciones Recientes</h3>
                <a href="{{ route('evaluations.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                    Ver todas →
                </a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($recentEvaluations as $evaluation)
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="avatar avatar-md bg-gray-100 dark:bg-gray-800">
                                {{ substr($evaluation->agent->name, 0, 2) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $evaluation->agent->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $evaluation->campaign->name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            @php
                                $scoreClass = match(true) {
                                    $evaluation->percentage_score >= 90 => 'score-excellent',
                                    $evaluation->percentage_score >= 80 => 'score-good',
                                    $evaluation->percentage_score >= 70 => 'score-average',
                                    default => 'score-poor',
                                };
                            @endphp
                            <span class="text-lg font-bold {{ $scoreClass }}">{{ number_format($evaluation->percentage_score, 0) }}%</span>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $evaluation->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-8">
                        <div class="empty-state-icon">
                            <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">No hay evaluaciones recientes</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Top Failures -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Top Falencias Detectadas</h3>
            </div>
            <div class="card-body space-y-5">
                @forelse($topFailures as $index => $failure)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs font-bold">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $failure->name }}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $failure->count }}</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-rose-500" style="width: {{ min(100, ($failure->count / max(1, $topFailures->max('count'))) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-8">
                        <div class="empty-state-icon bg-emerald-100 dark:bg-emerald-500/20">
                            <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">¡Excelente! No hay tendencias negativas</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
