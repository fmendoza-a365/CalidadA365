<x-app-layout>
    <x-slot name="header">Campañas</x-slot>



    <div class="card">
        <!-- Toolbar -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Campañas</h3>
                <span class="badge badge-neutral">{{ $campaigns->total() }}</span>
            </div>
            @if(auth()->user()->hasRole('admin'))
                <a href="{{ route('campaigns.create') }}" class="btn-primary btn-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nueva Campaña
                </a>
            @endif
        </div>

        <!-- Table -->
        <div class="table-container overflow-x-auto">
            <table class="table">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="w-1/4">Nombre</th>
                        <th class="w-1/3">Descripción</th>
                        <th class="text-center w-32">Estado</th>
                        <th class="text-center w-40">Ficha Activa</th>
                        <th class="text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-white font-bold text-sm shadow-sm"
                                        style="background-color: {{ $campaign->color }};">
                                        @if($campaign->logo_url)
                                            <img src="{{ $campaign->logo_url }}" alt=""
                                                class="w-full h-full rounded-full object-cover">
                                        @else
                                            {{ substr($campaign->name, 0, 2) }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $campaign->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $campaign->type }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <p class="text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                    {{ $campaign->description ?: 'Sin descripción' }}
                                </p>
                            </td>
                            <td class="text-center">
                                @if($campaign->is_active)
                                    <span class="badge badge-success">Activa</span>
                                @else
                                    <span class="badge badge-neutral">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($campaign->activeFormVersion)
                                    <svg class="w-4 h-4 text-emerald-500 inline-block" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400 ml-1">v{{ $campaign->activeFormVersion->version_number }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('campaigns.show', $campaign) }}" class="btn-ghost btn-sm"
                                    title="Ver Detalles">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                @if(auth()->user()->hasRole('admin'))
                                    <a href="{{ route('campaigns.edit', $campaign) }}" class="btn-ghost btn-sm text-indigo-600"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button type="button"
                                        @click="$dispatch('open-modal', 'delete-campaign-{{ $campaign->id }}')"
                                        class="btn-ghost btn-sm text-rose-600" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>

                                    <form id="delete-campaign-form-{{ $campaign->id }}" method="POST"
                                        action="{{ route('campaigns.destroy', $campaign) }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <div x-data="{}"
                                        x-on:confirm-action.window="if ($event.detail.name === 'delete-campaign-{{ $campaign->id }}') document.getElementById('delete-campaign-form-{{ $campaign->id }}').submit()">
                                        <x-confirm-modal name="delete-campaign-{{ $campaign->id }}" title="¿Eliminar campaña?"
                                            message="Esta acción eliminará la campaña y todas sus asignaciones. No podrás recuperarla."
                                            confirmText="Sí, eliminar campaña" type="danger" />
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 mb-3">No hay campañas registradas</p>
                                    @if(auth()->user()->hasRole('admin'))
                                        <a href="{{ route('campaigns.create') }}" class="btn-primary btn-md">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Crear primera campaña
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($campaigns->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>
</x-app-layout>