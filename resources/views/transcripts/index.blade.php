<x-app-layout>
    <x-slot name="header">Transcripciones</x-slot>



    <div class="card flex flex-col h-[calc(100vh-12rem)] overflow-hidden">
        <!-- Rigid Header (Toolbar + Filters) -->
        <div class="flex-none z-20 relative rounded-t-2xl bg-white dark:bg-[#141414]">
            <!-- Toolbar -->
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Transcripciones</h3>
                    <span class="badge badge-neutral">{{ $interactions->total() }}</span>
                </div>
                <a href="{{ route('transcripts.create') }}" class="btn-primary btn-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Cargar Nueva
                </a>
            </div>

            <!-- Filters -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/30 border-b border-gray-100 dark:border-gray-800">
                <form method="GET" action="{{ route('transcripts.index') }}"
                    class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1 min-w-0">
                        <label for="campaign_id" class="form-label">Campaña</label>
                        <select name="campaign_id" id="campaign_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Todas las campañas</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" {{ request('campaign_id') == $campaign->id ? 'selected' : '' }}>
                                    {{ $campaign->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label for="status" class="form-label">Estado</label>
                        <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendiente
                            </option>
                            <option value="evaluated" {{ request('status') == 'evaluated' ? 'selected' : '' }}>Evaluada
                            </option>
                        </select>
                    </div>
                    <a href="{{ route('transcripts.index') }}"
                        class="btn-secondary btn-md whitespace-nowrap">Limpiar</a>
                </form>
            </div>
        </div>

        <!-- Scrollable Table -->
        <div class="flex-1 overflow-x-auto overflow-y-auto min-h-0">
            <table class="table relative w-full">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="w-16">ID</th>
                        <th>Asesor</th>
                        <th>Campaña</th>
                        <th>Fecha Llamada</th>
                        <th class="text-center w-32">Estado</th>
                        <th class="text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interactions as $interaction)
                        <tr>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    @if($interaction->isAudio())
                                        <svg class="w-4 h-4 text-purple-500 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" title="Audio">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" title="Texto">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    @endif
                                    <span class="text-xs text-gray-500 font-mono">#{{ $interaction->id }}</span>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="font-medium text-gray-900 dark:text-white">{{ $interaction->agent?->name ?? '—' }}</span>
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $interaction->campaign?->name ?? '—' }}
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $interaction->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="text-center">
                                @if($interaction->isTranscribing())
                                    <span class="badge badge-info">Transcribiendo</span>
                                @elseif($interaction->isTranscriptionFailed())
                                    <span class="badge badge-danger">Error STT</span>
                                @elseif($interaction->evaluation)
                                    <span class="badge badge-success">Evaluada</span>
                                @else
                                    <span class="badge badge-warning">Pendiente</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- View -->
                                    <a href="{{ route('transcripts.show', $interaction) }}"
                                        class="btn-ghost btn-sm text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                        title="Ver Detalle">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <!-- Download -->
                                    <a href="{{ route('transcripts.download', $interaction) }}"
                                        class="btn-ghost btn-sm text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                        title="Descargar Original">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                    </a>

                                    <!-- Evaluate with AI (if not evaluated) -->
                                    @if(!$interaction->evaluation)
                                        <form method="POST" action="{{ route('transcripts.evaluate', $interaction) }}"
                                            class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="btn-ghost btn-sm text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400"
                                                title="Evaluar con IA">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Edit -->
                                    <a href="{{ route('transcripts.edit', $interaction) }}"
                                        class="btn-ghost btn-sm text-indigo-600 hover:text-indigo-700" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    <!-- Delete -->
                                    <button type="button"
                                        @click="$dispatch('open-modal', 'delete-transcript-{{ $interaction->id }}')"
                                        class="btn-ghost btn-sm text-rose-600 hover:text-rose-700" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>

                                    <form id="delete-form-{{ $interaction->id }}" method="POST"
                                        action="{{ route('transcripts.destroy', $interaction) }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <div x-data="{}"
                                        x-on:confirm-action.window="if ($event.detail.name === 'delete-transcript-{{ $interaction->id }}') document.getElementById('delete-form-{{ $interaction->id }}').submit()">
                                        <x-confirm-modal name="delete-transcript-{{ $interaction->id }}"
                                            title="¿Eliminar transcripción?"
                                            message="Esta acción eliminará la transcripción y su evaluación asociada permanentemente. ¿Deseas continuar?"
                                            confirmText="Sí, eliminar" type="danger" />
                                    </div>

                                    <!-- View Evaluation (if exists) -->
                                    @if($interaction->evaluation)
                                        <a href="{{ route('evaluations.show', $interaction->evaluation) }}"
                                            class="btn-ghost btn-sm text-emerald-600 hover:text-emerald-700"
                                            title="Ver Evaluación">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state py-12">
                                    <div class="empty-state-icon">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 mb-3">No hay transcripciones cargadas</p>
                                    <a href="{{ route('transcripts.create') }}" class="btn-primary btn-md">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        Cargar primera transcripción
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($interactions->hasPages())
            <div
                class="flex-none px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-[#141414] rounded-b-2xl">
                {{ $interactions->links() }}
            </div>
        @endif
    </div>
</x-app-layout>