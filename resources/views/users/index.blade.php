<x-app-layout>
    <x-slot name="header">Usuarios</x-slot>

    @php
        $roleLabel = function (?string $role): string {
            return [
                'admin' => 'Admin',
                'qa_manager' => 'QA Manager',
                'qa_coordinator' => 'Coordinador QA',
                'qa_monitor' => 'Monitor QA',
                'manager' => 'Gerente',
                'supervisor' => 'Supervisor',
                'agent' => 'Agente',
            ][$role] ?? ($role ? ucwords(str_replace('_', ' ', $role)) : 'Sin rol');
        };

        $roleTone = function (?string $role): string {
            return match ($role) {
                'admin' => 'badge-danger',
                'qa_manager', 'qa_coordinator', 'qa_monitor' => 'badge-info',
                'manager', 'supervisor' => 'badge-warning',
                'agent' => 'badge-neutral',
                default => 'badge-neutral',
            };
        };

        $activeFilterCount = collect(request()->only(['q', 'role']))->filter(fn ($value) => filled($value))->count();
    @endphp

    <div
        class="card overflow-hidden"
        x-data="{ selectedUsers: [], visibleUsers: @js($users->pluck('id')->reject(fn ($id) => $id === auth()->id())->map(fn ($id) => (string) $id)->values()) }"
    >
        <div class="card-header">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Usuarios</h3>
                        <span class="badge badge-neutral">{{ $users->total() }}</span>
                        @if($activeFilterCount > 0)
                            <span class="badge badge-info">{{ $activeFilterCount }} filtro(s)</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Administración de accesos, roles y datos de contacto.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button"
                        @click="$dispatch('open-modal', 'bulk-delete-users')"
                        :disabled="selectedUsers.length === 0"
                        class="btn-danger btn-md w-fit disabled:cursor-not-allowed disabled:opacity-40">
                        Eliminar seleccionados
                        <span x-show="selectedUsers.length > 0" x-text="selectedUsers.length"></span>
                    </button>

                    <a href="{{ route('users.create') }}" class="btn-primary btn-md w-fit">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo Usuario
                    </a>

                    <a href="{{ route('users.import') }}" class="btn-secondary btn-md w-fit">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v10m0-10l4 4m-4-4L8 8" />
                        </svg>
                        Importar usuarios
                    </a>
                </div>
            </div>
        </div>

        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(220px,1fr)_220px_auto]">
                <div>
                    <label for="q" class="form-label">Buscar</label>
                    <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-input"
                        placeholder="Nombre, usuario, email o área">
                </div>

                <div>
                    <label for="role" class="form-label">Rol</label>
                    <select name="role" id="role" class="form-select">
                        <option value="">Todos</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                                {{ $roleLabel($role->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary btn-md">Filtrar</button>
                    <a href="{{ route('users.index') }}" class="btn-secondary btn-md">Limpiar</a>
                </div>
            </form>
        </div>

        <form id="bulk-users-delete-form" method="POST" action="{{ route('users.bulk-destroy') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>

        <div x-data="{}" x-on:confirm-action.window="if ($event.detail.name === 'bulk-delete-users') document.getElementById('bulk-users-delete-form').submit()">
            <x-confirm-modal
                name="bulk-delete-users"
                title="¿Eliminar usuarios seleccionados?"
                message="Se eliminarán los usuarios seleccionados que no tengan historial operativo. Los usuarios con transcripciones, evaluaciones o reportes asociados serán omitidos."
                confirmText="Sí, eliminar seleccionados"
                type="danger"
            />
        </div>

        <div class="table-container border-0">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input
                                type="checkbox"
                                class="form-checkbox"
                                :checked="visibleUsers.length > 0 && visibleUsers.every(id => selectedUsers.includes(id))"
                                @change="selectedUsers = $event.target.checked ? Array.from(new Set([...selectedUsers, ...visibleUsers])) : selectedUsers.filter(id => !visibleUsers.includes(id))"
                            >
                        </th>
                        <th>Usuario</th>
                        <th class="w-36">Rol</th>
                        <th class="w-64">Campaña / Subcampaña</th>
                        <th class="w-52">Supervisor</th>
                        <th class="w-64">Contacto</th>
                        <th class="w-36 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $primaryRole = $user->getRoleNames()->first();
                            $phone = $user->company_phone ?: $user->personal_phone;

                            $campaignDisplay = '—';
                            $supervisorDisplay = '—';

                            if ($user->hasRole('agent')) {
                                $activeAssignment = $user->agentAssignments->where('is_active', true)->first();
                                if ($activeAssignment) {
                                    if ($activeAssignment->campaign) {
                                        $campaignDisplay = $activeAssignment->campaign->displayName();
                                    }
                                    if ($activeAssignment->supervisor) {
                                        $supervisorDisplay = $activeAssignment->supervisor->name;
                                    }
                                }
                            } elseif ($user->hasAnyRole(['qa_monitor', 'qa_coordinator', 'manager'])) {
                                $managed = $user->managedCampaigns;
                                if ($managed->isNotEmpty()) {
                                    $campaignDisplay = $managed->map(fn($c) => $c->displayName())->join(', ');
                                    if (strlen($campaignDisplay) > 50) {
                                        $campaignDisplay = substr($campaignDisplay, 0, 47) . '...';
                                    }
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                @if(auth()->id() !== $user->id)
                                    <input
                                        type="checkbox"
                                        name="user_ids[]"
                                        value="{{ $user->id }}"
                                        form="bulk-users-delete-form"
                                        class="form-checkbox"
                                        x-model="selectedUsers"
                                    >
                                @else
                                    <span class="text-xs text-gray-300 dark:text-gray-700">—</span>
                                @endif
                            </td>
                            <td class="wrap-text">
                                <div class="flex items-center gap-3">
                                    <img class="h-9 w-9 flex-shrink-0 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700"
                                        src="{{ $user->avatar_url }}"
                                        alt="{{ $user->name }}">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ trim($user->full_name) }}
                                        </div>
                                        <div class="truncate text-xs font-mono text-indigo-600 dark:text-indigo-400">
                                            {{ '@'.$user->username }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="badge {{ $roleTone($primaryRole) }}">
                                    {{ $roleLabel($primaryRole) }}
                                </span>
                            </td>

                            <td class="wrap-text">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $campaignDisplay }}
                                </span>
                            </td>

                            <td class="wrap-text">
                                @if($supervisorDisplay !== '—')
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $supervisorDisplay }}</div>
                                    @php
                                        $superAssignment = $user->agentAssignments->where('is_active', true)->first();
                                    @endphp
                                    @if($superAssignment && $superAssignment->supervisor)
                                        <div class="mt-0.5 text-xs font-mono text-gray-500 dark:text-gray-400">
                                            {{ '@' . $superAssignment->supervisor->username }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>

                            <td class="wrap-text">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $user->email }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $phone ?: 'Sin teléfono registrado' }}
                                </div>
                            </td>

                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('users.edit', $user) }}" class="btn-secondary btn-sm">
                                        Editar
                                    </a>

                                    @if(auth()->id() !== $user->id)
                                        <button type="button"
                                            @click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')"
                                            class="btn-ghost btn-sm text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
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
                            <td colspan="7">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <p class="font-medium text-gray-900 dark:text-white">No hay usuarios registrados</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta los filtros o crea un nuevo usuario.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
