<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Importar Usuarios</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Carga masiva de accesos del sistema.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">Volver</a>
                <a href="{{ route('users.create') }}" class="btn-secondary btn-sm">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo usuario
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="font-semibold">Hay errores en el formulario.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Roles disponibles</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($roles->count()) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Campañas generales</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($campaigns->count()) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Subcampañas</div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="badge badge-info">{{ number_format($subcampaigns->count()) }}</span>
                    <span class="badge badge-neutral">CSV</span>
                    <span class="badge badge-neutral">Excel</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Archivo y reglas</h3>
                                <span class="badge badge-neutral">Máx. 10 MB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Crea usuarios nuevos o actualiza existentes usando username o email.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('users.import.template') }}" class="btn-secondary btn-sm">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z" />
                                </svg>
                                CSV
                            </a>
                            <a href="{{ route('users.import.template.excel') }}" class="btn-secondary btn-sm">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z" />
                                </svg>
                                Excel
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('users.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div x-data="{ fileName: '', fileSize: '' }">
                            <label class="form-label">Archivo de usuarios <span class="text-rose-500">*</span></label>
                            <label class="group mt-2 flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-gray-500 dark:hover:bg-gray-900">
                                <input
                                    type="file"
                                    name="csv_file"
                                    accept=".csv,.txt,.xlsx,.xls,.ods"
                                    class="sr-only"
                                    required
                                    @change="
                                        const file = $event.target.files[0];
                                        fileName = file ? file.name : '';
                                        fileSize = file ? `${(file.size / 1024 / 1024).toFixed(2)} MB` : '';
                                    "
                                >
                                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white text-indigo-600 shadow-sm ring-1 ring-gray-200 transition group-hover:scale-105 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v10m0-10l4 4m-4-4L8 8" />
                                    </svg>
                                </span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="fileName || 'Seleccionar archivo'"></span>
                                <span class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="fileSize || 'CSV, TXT, XLSX, XLS u ODS'"></span>
                            </label>
                            @error('csv_file')<p class="form-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="form-label">Rol por defecto</label>
                                <select name="default_role" class="form-select">
                                    <option value="">Usar columna role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" @selected(old('default_role') === $role->name)>
                                            {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="form-label">Contraseña por defecto</label>
                                <input type="text" name="default_password" value="{{ old('default_password', 'Qa365.2026') }}" class="form-input" autocomplete="off">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se aplica cuando la fila no trae password.</p>
                            </div>
                        </div>

                        @php
                            $subcampaignsByParent = $subcampaigns->groupBy('parent_id');
                        @endphp
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3" x-data="{
                            parentCampaignId: '{{ old('default_campaign_id') }}',
                            subcampaignId: '{{ old('default_subcampaign_id') }}',
                            subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->displayName()])->values())) }},
                            get availableSubcampaigns() {
                                return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                            }
                        }">
                            <div>
                                <label class="form-label">Campaña general</label>
                                <select name="default_campaign_id" class="form-select" x-model="parentCampaignId" @change="subcampaignId = ''">
                                    <option value="">Sin campaña general fija</option>
                                    @foreach($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}">{{ $campaign->displayName() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Úsala como agrupador para filtrar la subcampaña destino.</p>
                            </div>

                            <div>
                                <label class="form-label">Subcampaña destino</label>
                                <select name="default_subcampaign_id" class="form-select disabled:opacity-50" x-model="subcampaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0">
                                    <option value="">Usar columna campaigns/subcampaigns</option>
                                    <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                        <option :value="sub.id" x-text="sub.name" :selected="subcampaignId == sub.id"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se aplica a todo el archivo como asignación operativa. Ejemplo: Claro / Upgrade.</p>
                            </div>

                            <div>
                                <label class="form-label">Supervisor por defecto</label>
                                <select name="default_supervisor_id" class="form-select">
                                    <option value="">Usar columna supervisor</option>
                                    @foreach($supervisors as $supervisor)
                                        <option value="{{ $supervisor->id }}" @selected((string) old('default_supervisor_id') === (string) $supervisor->id)>
                                            {{ $supervisor->name }} · {{ '@'.$supervisor->username }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Necesario para asignar asesores a campaña si el archivo no trae supervisor.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-950 dark:hover:border-gray-700">
                                <input type="hidden" name="update_existing" value="0">
                                <input type="checkbox" name="update_existing" value="1" class="form-checkbox mt-1" @checked(old('update_existing', '1'))>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">Actualizar existentes</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Mantiene un solo registro por username o email.</span>
                                </span>
                            </label>

                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-950 dark:hover:border-gray-700">
                                <input type="hidden" name="sync_campaigns" value="0">
                                <input type="checkbox" name="sync_campaigns" value="1" class="form-checkbox mt-1" @checked(old('sync_campaigns', '1'))>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">Asignar campañas</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Usa destino seleccionado o columna campaigns/subcampaigns.</span>
                                </span>
                            </label>
                        </div>

                        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                            <a href="{{ route('users.index') }}" class="btn-secondary btn-md justify-center">Cancelar</a>
                            <button type="submit" class="btn-primary btn-md justify-center">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v10m0-10l4 4m-4-4L8 8" />
                                </svg>
                                Importar usuarios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Columnas esperadas</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La plantilla ya incluye estos encabezados.</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container border-0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Campo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono text-xs">name</td>
                                        <td><span class="badge badge-danger">Requerido</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">role</td>
                                        <td><span class="badge badge-warning">O por defecto</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">email / username</td>
                                        <td><span class="badge badge-danger">Uno requerido</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">password</td>
                                        <td><span class="badge badge-neutral">Opcional</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">campaigns</td>
                                        <td><span class="badge badge-neutral">Opcional</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">subcampaigns</td>
                                        <td><span class="badge badge-neutral">Opcional</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono text-xs">supervisor_username</td>
                                        <td><span class="badge badge-neutral">Opcional</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Roles</h3>
                    </div>
                    <div class="card-body">
                        <div class="flex max-h-32 flex-wrap gap-2 overflow-y-auto pr-1">
                            @forelse($roles as $role)
                                <span class="badge badge-info">{{ $role->name }}</span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">Sin roles registrados.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Campañas y subcampañas</h3>
                    </div>
                    <div class="card-body">
                        <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto pr-1">
                            @forelse($campaigns as $campaign)
                                <span class="badge badge-neutral">{{ $campaign->displayName() }}</span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">No hay campañas generales activas.</span>
                            @endforelse
                            @foreach($subcampaigns as $subcampaign)
                                <span class="badge badge-info">{{ $subcampaign->displayName() }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
