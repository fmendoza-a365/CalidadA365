<x-app-layout>
    <x-slot name="header">{{ $campaign->name }}</x-slot>

    @if(session('success'))
        <div class="alert alert-success mb-6">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card stat-card-indigo">
            <div class="stat-label">Interacciones</div>
            <div class="stat-value">{{ number_format($stats['total_interactions']) }}</div>
        </div>
        <div class="stat-card stat-card-emerald">
            <div class="stat-label">Evaluaciones</div>
            <div class="stat-value">{{ number_format($stats['total_evaluations']) }}</div>
        </div>
        <div class="stat-card stat-card-blue">
            <div class="stat-label">Promedio</div>
            <div class="stat-value">{{ $stats['avg_score'] }}%</div>
        </div>
        <div class="stat-card stat-card-amber">
            <div class="stat-label">Asesores Activos</div>
            <div class="stat-value">{{ $stats['active_agents'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Información -->
        <div class="card lg:col-span-1">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Información</h3>
                <a href="{{ route('campaigns.edit', $campaign) }}" class="btn-ghost btn-sm">Editar</a>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Estado</div>
                    @if($campaign->is_active)
                        <span class="badge badge-success">Activa</span>
                    @else
                        <span class="badge badge-neutral">Inactiva</span>
                    @endif
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Descripción</div>
                    <p class="text-gray-900 dark:text-white">{{ $campaign->description ?: 'Sin descripción' }}</p>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Ficha de Calidad</div>
                    @if($campaign->activeFormVersion)
                        <p class="text-gray-900 dark:text-white">{{ $campaign->activeFormVersion->form->name }}</p>
                        <span class="badge badge-info">v{{ $campaign->activeFormVersion->version_number }}</span>
                    @else
                        <span class="text-rose-600 dark:text-rose-400">Sin ficha asignada</span>
                    @endif
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Creada</div>
                    <p class="text-gray-900 dark:text-white">{{ $campaign->created_at->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>

        <!-- Asignaciones -->
        <div class="card lg:col-span-2">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Asignaciones de Asesores</h3>
                <div class="flex gap-2">
                    <a href="{{ route('campaigns.assignments.index', $campaign) }}" class="btn-ghost btn-sm text-indigo-600">
                        Ver todas / Gestionar
                    </a>
                    <a href="{{ route('campaigns.assignments.create', $campaign) }}" class="btn-primary btn-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nueva Asignación
                    </a>
                </div>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asesor</th>
                            <th>Supervisor</th>
                            <th class="text-center">Estado</th>
                            <th>Inicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaign->assignments as $assignment)
                            <tr>
                                <td class="font-medium text-gray-900 dark:text-white">{{ $assignment->agent->name }}</td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $assignment->supervisor->name }}</td>
                                <td class="text-center">
                                    @if($assignment->is_active)
                                        <span class="badge badge-success">Activa</span>
                                    @else
                                        <span class="badge badge-neutral">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-gray-500 dark:text-gray-400">{{ $assignment->start_date?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-8">
                                        <p class="text-gray-500 dark:text-gray-400">No hay asesores asignados</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-6">
        <a href="{{ route('campaigns.index') }}" class="btn-secondary btn-md">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver al Listado
        </a>
    </div>
</x-app-layout>
