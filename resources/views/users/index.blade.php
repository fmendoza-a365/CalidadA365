<x-app-layout>
    <x-slot name="header">Usuarios</x-slot>

    <div class="card">
        <!-- Toolbar -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Usuarios</h3>
                <span class="badge badge-neutral">{{ $users->total() }}</span>
            </div>
            <a href="{{ route('users.create') }}" class="btn-primary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Usuario
            </a>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-1/3 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th class="w-1/6 px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                        <th class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                        <th class="w-1/6 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <!-- User Info -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-12 w-12 relative">
                                        <img class="h-12 w-12 rounded-full object-cover {{ $user->frame_class }}" 
                                             src="{{ $user->avatar_url }}" 
                                             alt="{{ $user->name }}">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ $user->full_name }}
                                        </div>
                                        <div class="text-xs font-mono text-indigo-600 dark:text-indigo-400">
                                            {{ '@' . $user->username }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $user->email }}
                                        </div>
                                        @if($user->personal_phone || $user->company_phone)
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            📞 {{ $user->company_phone ?? $user->personal_phone }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 uppercase">
                                    {{ $user->getRoleNames()->first() ?? 'Sin Rol' }}
                                </span>
                            </td>

                            <!-- Location -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($user->department)
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $user->department }}</span>
                                        <span class="text-xs">{{ $user->province }} @if($user->district) - {{ $user->district }} @endif</span>
                                    </div>
                                @else
                                    <span class="italic text-gray-400 text-xs">No registrado</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-3 items-center">
                                    <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                        Editar
                                    </a>

                                    @if(auth()->id() !== $user->id)
                                        <button type="button" 
                                            @click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')" 
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-medium">
                                            Eliminar
                                        </button>

                                        <form id="delete-user-form-{{ $user->id }}" 
                                            method="POST" 
                                            action="{{ route('users.destroy', $user) }}" 
                                            class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>

                                        <div x-data="{}" x-on:confirm-action.window="if ($event.detail.name === 'delete-user-{{ $user->id }}') document.getElementById('delete-user-form-{{ $user->id }}').submit()">
                                            <x-confirm-modal 
                                                name="delete-user-{{ $user->id }}" 
                                                title="¿Eliminar usuario?" 
                                                message="Esta acción no se puede deshacer. ¿Estás seguro de que deseas eliminar a {{ $user->name }}?"
                                                confirmText="Sí, eliminar usuario"
                                                type="danger"
                                            />
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400">No hay usuarios registrados</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
