<x-app-layout>
    <x-slot name="header">Asignaciones - {{ $campaign->name }}</x-slot>

    <div class="card">
        <!-- Toolbar -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">Asesores Asignados</h3>
                <span class="badge badge-neutral">{{ $assignments->total() }}</span>
            </div>
            <a href="{{ route('campaigns.assignments.create', $campaign) }}" class="btn-primary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Asignación
            </a>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Asesor</th>
                        <th>Supervisor</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr>
                            <td class="font-medium text-gray-900 dark:text-white">
                                {{ $assignment->agent->name }}
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $assignment->supervisor->name }}
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $assignment->start_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $assignment->end_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="text-center">
                                @if($assignment->is_active)
                                    <span class="badge badge-success">Activa</span>
                                @else
                                    <span class="badge badge-neutral">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('assignments.edit', $assignment) }}" class="btn-ghost btn-sm text-indigo-600" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    
                                    <button type="button" 
                                        @click="$dispatch('open-modal', 'delete-assignment-{{ $assignment->id }}')" 
                                        class="btn-ghost btn-sm text-rose-600" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>

                                    <form id="delete-assignment-form-{{ $assignment->id }}" 
                                        method="POST" 
                                        action="{{ route('assignments.destroy', $assignment) }}" 
                                        class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <div x-data="{}" x-on:confirm-action.window="if ($event.detail.name === 'delete-assignment-{{ $assignment->id }}') document.getElementById('delete-assignment-form-{{ $assignment->id }}').submit()">
                                        <x-confirm-modal 
                                            name="delete-assignment-{{ $assignment->id }}" 
                                            title="¿Quitar asignación?" 
                                            message="El asesor ya no podrá acceder a esta campaña. ¿Deseas continuar?"
                                            confirmText="Sí, quitar"
                                            type="danger"
                                        />
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 mb-3">No hay asesores asignados a esta campaña</p>
                                    <a href="{{ route('campaigns.assignments.create', $campaign) }}" class="btn-primary btn-md">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Asignar Asesor
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assignments->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>

    <div class="mt-6">
        <a href="{{ route('campaigns.show', $campaign) }}" class="btn-secondary btn-md">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver a la Campaña
        </a>
    </div>
</x-app-layout>
