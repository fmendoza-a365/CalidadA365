<x-app-layout>
    <x-slot name="header">Fichas de Calidad</x-slot>



    <div class="card">
        <!-- Toolbar -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Fichas</h3>
                <span class="badge badge-neutral">{{ $forms->total() }}</span>
            </div>
            <a href="{{ route('quality-forms.create') }}" class="btn-primary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Ficha
            </a>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="table">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="w-1/3">Nombre</th>
                        <th class="w-1/3">Campaña</th>
                        <th class="text-center w-32">Versión</th>
                        <th class="text-center w-32">Estado</th>
                        <th class="text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forms as $form)
                        <tr>
                            <td>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $form->name }}</span>
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $form->campaign->name }}
                            </td>
                            <td class="text-center">
                                @if($form->latestVersion)
                                    <span class="badge badge-neutral">v{{ $form->latestVersion->version_number }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($form->latestVersion)
                                    @if($form->latestVersion->status === 'published')
                                        <span class="badge badge-success">Publicada</span>
                                    @else
                                        <span class="badge badge-warning">Borrador</span>
                                    @endif
                                @else
                                    <span class="badge badge-neutral">Sin versión</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('quality-forms.show', $form) }}" class="btn-ghost btn-sm" title="Ver Detalles">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('quality-forms.edit', $form) }}" class="btn-ghost btn-sm text-indigo-600" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button type="button" 
                                        @click="$dispatch('open-modal', 'delete-form-{{ $form->id }}')" 
                                        class="btn-ghost btn-sm text-rose-600" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>

                                    <form id="delete-quality-form-{{ $form->id }}" 
                                        method="POST" 
                                        action="{{ route('quality-forms.destroy', $form) }}" 
                                        class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <div x-data="{}" x-on:confirm-action.window="if ($event.detail.name === 'delete-form-{{ $form->id }}') document.getElementById('delete-quality-form-{{ $form->id }}').submit()">
                                        <x-confirm-modal 
                                            name="delete-form-{{ $form->id }}" 
                                            title="¿Eliminar ficha de calidad?" 
                                            message="Solo se puede eliminar si no tiene evaluaciones asociadas. ¿Deseas continuar?"
                                            confirmText="Sí, eliminar"
                                            type="danger"
                                        />
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 mb-3">No hay fichas de calidad</p>
                                    <a href="{{ route('quality-forms.create') }}" class="btn-primary btn-md">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Crear primera ficha
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($forms->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $forms->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
