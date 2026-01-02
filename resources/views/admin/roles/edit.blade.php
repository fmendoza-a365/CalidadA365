<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Editar Permisos: {{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
            <a href="{{ route('roles.index') }}" class="btn-secondary btn-sm">Volver</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <form method="POST" action="{{ route('roles.update', $role) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($groupedPermissions as $group => $permissions)
                <div class="card h-full">
                    <div class="card-header bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700">
                        <h4 class="font-semibold text-gray-900 dark:text-white capitalize flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            {{ ucfirst($group) }}
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="space-y-3">
                            @foreach($permissions as $permission)
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="perm_{{ $permission->id }}" name="permissions[]" value="{{ $permission->name }}" type="checkbox"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                        {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="perm_{{ $permission->id }}" class="font-medium text-gray-700 dark:text-gray-300 select-none cursor-pointer">
                                        {{ $permission->name }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="fixed bottom-0 left-0 right-0 p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] flex justify-end gap-3 z-40 lg:pl-72">
                 <a href="{{ route('roles.index') }}" class="btn-secondary btn-md">Cancelar</a>
                 <button type="submit" class="btn-primary btn-md">Guardar Permisos</button>
            </div>
            
            <!-- Spacer for fixed footer -->
            <div class="h-16"></div>
        </form>
    </div>
</x-app-layout>
