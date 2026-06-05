<x-app-layout>
    <x-slot name="header">Plan de Muestreo</x-slot>

    @php
        $statusClasses = [
            \App\Models\SamplingOrder::STATUS_PENDING => 'badge badge-neutral',
            \App\Models\SamplingOrder::STATUS_APPLIED => 'badge badge-success',
            \App\Models\SamplingOrder::STATUS_NOT_APPLIED => 'badge badge-danger',
            \App\Models\SamplingOrder::STATUS_JUSTIFIED => 'badge badge-warning',
        ];
        $statusOptions = [
            \App\Models\SamplingOrder::STATUS_PENDING => 'Pendiente',
            \App\Models\SamplingOrder::STATUS_APPLIED => 'Aplicado',
            \App\Models\SamplingOrder::STATUS_NOT_APPLIED => 'No aplicado',
            \App\Models\SamplingOrder::STATUS_JUSTIFIED => 'Justificado',
        ];
        $reasonOptions = [
            'No hubo llamada válida',
            'Asesor ausente',
            'Incidencia técnica',
            'Llamada fuera de alcance',
            'Cliente no contactado',
            'Otro',
        ];
        $coverage = $summary['orders'] > 0 ? round(($summary['applied'] / $summary['orders']) * 100, 1) : 0;
        $quotas = $samplingPlan->quotas ?? [];
        $latestEvents = $samplingPlan->orders
            ->flatMap(fn ($order) => $order->auditEvents->map(fn ($event) => ['order' => $order, 'event' => $event]))
            ->sortByDesc(fn ($row) => $row['event']->occurred_at)
            ->take(8);
    @endphp

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card overflow-hidden">
            <div class="card-body">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">
                            Semana {{ $samplingPlan->week_start->format('d/m/Y') }} - {{ $samplingPlan->week_end->format('d/m/Y') }}
                        </div>
                        <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $samplingPlan->name ?: 'Muestreo aleatorio QA' }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <span>{{ $samplingPlan->campaign?->displayName() ?? 'Todas las campañas' }}</span>
                            <span>·</span>
                            <span>{{ $samplingPlan->staff_count }} asesores</span>
                            <span>·</span>
                            <span>{{ $samplingPlan->business_days }}</span>
                            <span>·</span>
                            <span>{{ substr((string) $samplingPlan->start_hour, 0, 5) }} - {{ substr((string) $samplingPlan->end_hour, 0, 5) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row xl:items-center">
                        <a href="{{ route('sampling.index') }}" class="btn-secondary btn-md">Nuevo plan</a>
                        <a href="{{ route('sampling.orders.export', $samplingPlan) }}" class="btn-primary btn-md">Exportar órdenes</a>
                        <a href="{{ route('sampling.audit.export', $samplingPlan) }}" class="btn-secondary btn-md">Exportar auditoría</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-6">
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cobertura</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $coverage }}%</div>
                    <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ min(100, $coverage) }}%"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Órdenes</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $summary['orders'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Generadas</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aplicadas</div>
                    <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $summary['applied'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Con ID de llamada</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pendientes</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $summary['pending'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Por ejecutar</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No aplicadas</div>
                    <div class="mt-2 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ $summary['not_applied'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Con motivo</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cuotas</div>
                    <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        Q1 {{ $quotas['Q1'] ?? 1 }} · Q2 {{ $quotas['Q2'] ?? 2 }}
                    </div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        Q3 {{ $quotas['Q3'] ?? 3 }} · Q4 {{ $quotas['Q4'] ?? 4 }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Órdenes de muestreo</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Cada orden define cuándo y cómo seleccionar la llamada. El ID de llamada queda enlazado si existe una interacción cargada.
                            </p>
                        </div>
                        <form method="GET" action="{{ route('sampling.show', $samplingPlan) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_180px_auto] xl:w-[720px]">
                            <div>
                                <label class="form-label">Buscar</label>
                                <input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Asesor, supervisor, regla u orden">
                            </div>
                            <div>
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="btn-primary btn-md">Filtrar</button>
                                <a href="{{ route('sampling.show', $samplingPlan) }}" class="btn-secondary btn-md">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-container border-0 rounded-none">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Asesor</th>
                                <th>Fecha</th>
                                <th>Regla</th>
                                <th>Estado</th>
                                <th>Registro</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        @forelse($orders as $order)
                            <tbody x-data="{ open: false }">
                                <tr>
                                    <td>
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $order->order_code }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->quartile }} · {{ $order->campaign_name ?: 'Sin campaña' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $order->advisor_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->advisor_code }} · {{ $order->supervisor_name ?: 'Sin supervisor' }}</div>
                                    </td>
                                    <td>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $order->assigned_date->format('d/m/Y') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->assigned_day }}</div>
                                    </td>
                                    <td class="wrap-text">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $order->rule_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->rule_params }}</div>
                                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $order->instruction }}</div>
                                    </td>
                                    <td>
                                        <span class="{{ $statusClasses[$order->status] ?? 'badge badge-neutral' }}">
                                            {{ \App\Models\SamplingOrder::statusLabel($order->status) }}
                                        </span>
                                    </td>
                                    <td class="wrap-text">
                                        @if($order->status === \App\Models\SamplingOrder::STATUS_APPLIED)
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $order->call_identifier }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $order->registered_at?->format('d/m/Y H:i') }} · {{ $order->evaluator?->name ?? $order->evaluator_name }}
                                            </div>
                                            @if($order->interaction)
                                                <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                                    <a href="{{ route('transcripts.show', $order->interaction) }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Ver interacción</a>
                                                    @if($order->interaction->isAudio())
                                                        <a href="{{ route('transcripts.audio', $order->interaction) }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Audio</a>
                                                    @endif
                                                </div>
                                            @endif
                                        @elseif($order->status !== \App\Models\SamplingOrder::STATUS_PENDING)
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $order->reason ?: 'Sin motivo' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->registered_at?->format('d/m/Y H:i') }} · {{ $order->evaluator?->name ?? $order->evaluator_name }}</div>
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Pendiente de registro</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <button type="button"
                                            @click="open = !open"
                                            class="{{ $order->status === \App\Models\SamplingOrder::STATUS_PENDING ? 'btn-primary' : 'btn-secondary' }} btn-sm whitespace-nowrap"
                                            :aria-expanded="open.toString()">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6M9 8h6m2 13H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span x-show="!open">{{ $order->status === \App\Models\SamplingOrder::STATUS_PENDING ? 'Registrar auditoría' : 'Editar registro' }}</span>
                                            <span x-show="open" x-cloak>Cerrar</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak>
                                    <td colspan="7" class="wrap-text bg-gray-50/80 p-4 dark:bg-gray-900/60">
                                        <div class="rounded-xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-[#111111]">
                                            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h4 class="font-semibold text-gray-900 dark:text-white">Registrar auditoría de esta orden</h4>
                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        Marca como aplicada cuando tengas el SN o ID de la llamada. Usa motivo si no se pudo ejecutar o quedó justificada.
                                                    </p>
                                                </div>
                                                <button type="button" @click="open = false" class="btn-ghost btn-sm">Cerrar panel</button>
                                            </div>
                                            <form method="POST" action="{{ route('sampling.orders.update', $order) }}" class="grid grid-cols-1 gap-3 xl:grid-cols-[180px_220px_220px_minmax(260px,1fr)_auto] xl:items-end">
                                                    @csrf
                                                    <div>
                                                        <label class="form-label">Resultado</label>
                                                        <select name="status" class="form-select">
                                                            @foreach($statusOptions as $value => $label)
                                                                <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">SN / ID llamada</label>
                                                        <input type="text" name="call_identifier" value="{{ $order->call_identifier }}" class="form-input" placeholder="SN, external ID o interacción">
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Motivo</label>
                                                        <select name="reason" class="form-select">
                                                            <option value="">Sin motivo</option>
                                                            @foreach($reasonOptions as $reason)
                                                                <option value="{{ $reason }}" @selected($order->reason === $reason)>{{ $reason }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Observación</label>
                                                        <input type="text" name="comment" value="{{ $order->comment }}" class="form-input" placeholder="Comentario operativo opcional">
                                                    </div>
                                                    <button type="submit" class="btn-primary btn-md whitespace-nowrap">Guardar auditoría</button>
                                                </form>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No hay órdenes con los filtros actuales.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>

                @if($orders->hasPages())
                    <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                        {{ $orders->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <details class="card" open>
                    <summary class="card-header cursor-pointer list-none">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Cobertura por asesor</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cumplimiento semanal contra cuota.</p>
                            </div>
                            <span class="badge badge-neutral">{{ $summary['agents'] }}</span>
                        </div>
                    </summary>
                    <div class="card-body">
                        <div class="space-y-3">
                            @foreach($summary['rows'] as $row)
                                @php
                                    $rowCoverage = $row['required'] > 0 ? min(100, round(($row['applied'] / $row['required']) * 100, 1)) : 0;
                                @endphp
                                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $row['advisor_name'] }}</div>
                                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $row['advisor_code'] }} · {{ $row['quartile'] }} · {{ $row['campaign'] ?: 'Sin campaña' }}</div>
                                        </div>
                                        <span class="{{ $row['status'] === 'Cumple' ? 'badge badge-success' : ($row['status'] === 'Cumple con justificación' ? 'badge badge-warning' : 'badge badge-neutral') }}">
                                            {{ $row['status'] }}
                                        </span>
                                    </div>
                                    <div class="mt-3 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $rowCoverage }}%"></div>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['applied'] }}/{{ $row['required'] }} aplicadas · {{ $row['pending'] }} pendientes · {{ $row['justified'] }} justificadas
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>

                <div class="card">
                    <div class="card-header">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Auditoría reciente</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Últimos movimientos del plan.</p>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            @forelse($latestEvents as $row)
                                <div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $row['event']->event }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['event']->occurred_at?->format('d/m H:i') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['order']->order_code }} · {{ $row['event']->actor?->name ?? 'Sistema' }}
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    Sin eventos de auditoría.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
