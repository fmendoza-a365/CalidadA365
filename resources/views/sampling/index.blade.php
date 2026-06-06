<x-app-layout>
    <x-slot name="header">Muestreo QA</x-slot>

    @php
        $parentCampaigns = $campaigns->whereNull('parent_id');
        $subcampaignsByParent = $campaigns->whereNotNull('parent_id')->groupBy('parent_id');
    @endphp

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Muestreo aleatorio QA</h2>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">Carga la dotación por archivo y genera órdenes semanales con cuotas por cuartil y semilla auditable.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sampling.staffing.template') }}" class="btn-secondary btn-sm">Plantilla CSV</a>
                <a href="{{ route('sampling.staffing.template.excel') }}" class="btn-secondary btn-sm">Plantilla Excel</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Planes generados</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($plans->total()) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Dotaciones activas</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($staffingBatches->count()) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Campañas visibles</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($campaigns->count()) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="space-y-6">
                <div class="card">
                    <div class="card-header">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">1. Cargar dotación</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sube asesores, supervisores, campaña y cuartil desde CSV o Excel.</p>
                            </div>
                            <span class="badge badge-info">Archivo</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('sampling.staffing.store') }}" enctype="multipart/form-data" class="space-y-5">
                            @csrf

                            <div>
                                <label class="form-label">Nombre de la dotación</label>
                                <input type="text" name="name" value="{{ old('name', 'Dotación '.now()->format('d/m/Y')) }}" class="form-input" required>
                                @error('name')<p class="form-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="form-label">Vigencia desde</label>
                                    <input type="date" name="period_start" value="{{ old('period_start', now()->toDateString()) }}" class="form-input">
                                    @error('period_start')<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="form-label">Vigencia hasta</label>
                                    <input type="date" name="period_end" value="{{ old('period_end') }}" class="form-input">
                                    @error('period_end')<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="md:col-span-2" x-data="{
                                    parentCampaignId: '{{ old('parent_campaign_id') }}',
                                    campaignId: '{{ old('campaign_id') }}',
                                    subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                                    get availableSubcampaigns() {
                                        return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                                    },
                                    get resolvedCampaignId() {
                                        if (this.parentCampaignId && this.availableSubcampaigns.length === 0) {
                                            return this.parentCampaignId;
                                        }
                                        return this.campaignId;
                                    }
                                }">
                                    <input type="hidden" name="campaign_id" :value="resolvedCampaignId">
                                    <label class="form-label">Campaña / Subcampaña fija</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <select x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                                                <option value="">Tomar desde el archivo (Campaña)</option>
                                                @foreach($parentCampaigns as $pCamp)
                                                    <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <select x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                                                <option value="">Tomar desde el archivo (Subcampaña)</option>
                                                <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                                    <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                    @error('campaign_id')<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div x-data="{ fileName: '' }" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950">
                                <label class="form-label">Archivo de dotación</label>
                                <label class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-6 text-center transition hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-500/10">
                                    <input type="file" name="csv_file" accept=".csv,.txt,.xlsx,.xls,.ods" class="sr-only" required @change="fileName = $event.target.files[0]?.name || ''">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="fileName || 'Seleccionar CSV o Excel'"></span>
                                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">CSV, TXT, XLSX, XLS, ODS · máximo 10 MB</span>
                                </label>
                                @error('csv_file')<p class="form-error">{{ $message }}</p>@enderror
                            </div>

                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                <input type="hidden" name="sync_assignments" value="0">
                                <input type="checkbox" name="sync_assignments" value="1" class="form-checkbox mt-1" @checked(old('sync_assignments', '1'))>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">Sincronizar campaña-agente-supervisor</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Actualiza asignaciones si los usuarios ya existen.</span>
                                </span>
                            </label>

                            <div>
                                <label class="form-label">Notas</label>
                                <textarea name="notes" rows="2" class="form-textarea" placeholder="Ej. corte semanal, fuente, responsable">{{ old('notes') }}</textarea>
                                @error('notes')<p class="form-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-md">Cargar dotación</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">2. Generar plan semanal</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Selecciona una dotación cargada y define cuotas por cuartil.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('sampling.store') }}" class="space-y-5">
                            @csrf

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" name="name" value="{{ old('name') }}" class="form-input" placeholder="Ej. Muestreo semanal ventas">
                                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="form-label">Inicio de semana</label>
                                    <input type="date" name="week_start" value="{{ old('week_start', now()->startOfWeek()->format('Y-m-d')) }}" class="form-input" required>
                                    @error('week_start')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="form-label">Días laborables</label>
                                    <select name="business_days" class="form-select">
                                        <option value="mon-fri" @selected(old('business_days', 'mon-fri') === 'mon-fri')>Lunes a viernes</option>
                                        <option value="mon-sat" @selected(old('business_days') === 'mon-sat')>Lunes a sábado</option>
                                        <option value="all" @selected(old('business_days') === 'all')>Todos los días</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Hora mínima</label>
                                    <input type="time" name="start_hour" value="{{ old('start_hour', '09:00') }}" class="form-input" required>
                                </div>

                                <div>
                                    <label class="form-label">Hora máxima</label>
                                    <input type="time" name="end_hour" value="{{ old('end_hour', '18:00') }}" class="form-input" required>
                                </div>

                                <div class="md:col-span-2" x-data="{
                                    parentCampaignId: '{{ old('parent_campaign_id') }}',
                                    campaignId: '{{ old('campaign_id') }}',
                                    subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                                    get availableSubcampaigns() {
                                        return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                                    },
                                    get resolvedCampaignId() {
                                        if (this.parentCampaignId && this.availableSubcampaigns.length === 0) {
                                            return this.parentCampaignId;
                                        }
                                        return this.campaignId;
                                    }
                                }">
                                    <input type="hidden" name="campaign_id" :value="resolvedCampaignId">
                                    <label class="form-label">Campaña / Subcampaña</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <select x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                                                <option value="">Todas las campañas de la dotación</option>
                                                @foreach($parentCampaigns as $pCamp)
                                                    <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <select x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                                                <option value="">Todas las subcampañas de la dotación</option>
                                                <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                                    <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                    @error('campaign_id')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="form-label">Dotación</label>
                                    <select name="staffing_batch_id" class="form-select" required>
                                        <option value="">Selecciona una dotación cargada</option>
                                        @foreach($staffingBatches as $batch)
                                            <option value="{{ $batch->id }}" @selected((string) old('staffing_batch_id') === (string) $batch->id)>
                                                {{ $batch->name }} · {{ $batch->campaign?->displayName() ?? 'Todas' }} · {{ $batch->active_members_count }} activos
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('staffing_batch_id')<p class="form-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="form-label">Semilla de auditoría</label>
                                    <input type="text" name="seed" value="{{ old('seed') }}" class="form-input" placeholder="Opcional">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Misma semilla + misma dotación produce el mismo sorteo.</p>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="form-label mb-0">Cuotas por cuartil</label>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Q1=1 · Q2=2 · Q3=3 · Q4=4 por defecto</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                    @foreach(['q1' => 1, 'q2' => 2, 'q3' => 3, 'q4' => 4] as $quartile => $default)
                                        <div>
                                            <label class="form-label">{{ strtoupper($quartile) }}</label>
                                            <input type="number" name="quotas[{{ $quartile }}]" min="0" max="7" value="{{ old('quotas.'.$quartile, $default) }}" class="form-input">
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                    <input type="hidden" name="unique_day" value="0">
                                    <input type="checkbox" name="unique_day" value="1" class="form-checkbox mt-1" @checked(old('unique_day', '1'))>
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">Máximo 1 evaluación por día</span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Evita concentración en una misma fecha.</span>
                                    </span>
                                </label>

                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                    <input type="hidden" name="rotate_methods" value="0">
                                    <input type="checkbox" name="rotate_methods" value="1" class="form-checkbox mt-1" @checked(old('rotate_methods', '1'))>
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">Métodos rotativos</span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Alterna reglas para reducir patrones predecibles.</span>
                                    </span>
                                </label>
                            </div>

                            @if($staffingBatches->isEmpty())
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">Primero carga una dotación para poder generar el plan.</div>
                            @endif

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-md" @disabled($staffingBatches->isEmpty())>Generar órdenes aleatorias</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Planes generados</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Histórico de semanas y estado de ejecución.</p>
                            </div>
                            <form method="GET" action="{{ route('sampling.index') }}" class="w-full md:w-[400px] flex gap-2" x-data="{
                                parentCampaignId: '{{ request('parent_campaign_id') }}',
                                campaignId: '{{ request('campaign_id') }}',
                                subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                                get availableSubcampaigns() {
                                    return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                                }
                            }">
                                <select name="parent_campaign_id" x-model="parentCampaignId" 
                                        @change="campaignId = ''; $nextTick(() => $el.form.submit())"
                                        class="form-select text-sm py-1.5">
                                    <option value="">Todas las campañas</option>
                                    @foreach($parentCampaigns as $pCamp)
                                        <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                                    @endforeach
                                </select>

                                <select name="campaign_id" x-model="campaignId"
                                        @change="$el.form.submit()"
                                        :disabled="!parentCampaignId || availableSubcampaigns.length === 0"
                                        class="form-select text-sm py-1.5 disabled:opacity-50">
                                    <option value="">Todas las subcampañas</option>
                                    <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                        <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                                    </template>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            @forelse($plans as $plan)
                                @php
                                    $coverage = $plan->orders_count > 0 ? round(($plan->applied_orders_count / $plan->orders_count) * 100, 1) : 0;
                                @endphp
                                <a href="{{ route('sampling.show', $plan) }}" class="block rounded-lg border border-gray-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30 dark:border-gray-800 dark:hover:border-indigo-500/30 dark:hover:bg-indigo-500/5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $plan->name ?: 'Plan '.$plan->week_start->format('d/m/Y') }}</div>
                                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $plan->week_start->format('d/m/Y') }} - {{ $plan->week_end->format('d/m/Y') }}
                                                · {{ $plan->campaign?->displayName() ?? 'Todas' }}
                                            </div>
                                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $plan->staff_count }} asesores · {{ $plan->orders_count }} órdenes · semilla {{ $plan->seed ?: 'auto' }}
                                            </div>
                                        </div>
                                        <div class="w-28 text-right">
                                            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $coverage }}%</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">aplicado</div>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="empty-state py-12">
                                    <p class="font-medium text-gray-900 dark:text-white">Aún no hay planes de muestreo.</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Carga una dotación y genera la primera semana.</p>
                                </div>
                            @endforelse
                        </div>

                        @if($plans->hasPages())
                            <div class="mt-4">{{ $plans->links() }}</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Dotaciones activas</h3>
                    </div>
                    <div class="table-container border-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Dotación</th>
                                    <th>Campaña</th>
                                    <th>Activos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($staffingBatches as $batch)
                                    <tr>
                                        <td class="wrap-text">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $batch->name }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $batch->source_filename ?: 'Archivo cargado' }}</div>
                                        </td>
                                        <td>{{ $batch->campaign?->displayName() ?? $batch->campaign_name ?? 'Mixta' }}</td>
                                        <td><span class="badge badge-success">{{ $batch->active_members_count }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No hay dotaciones activas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
