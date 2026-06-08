<x-app-layout>
    <x-slot name="header">Transcripciones</x-slot>

    @php
        $shouldAutoRefreshTranscripts = $interactions->getCollection()->contains(
            fn ($interaction) => $interaction->isTranscribing() || ($interaction->isAudio() && ! $interaction->evaluation)
        );
    @endphp

    <div class="card">
        @php
            $activeFilterCount = collect([request('search'), request('parent_campaign_id'), request('campaign_id'), request('status'), request('channel'), request('priority'), request('uploaded_by')])->filter()->count();
            $parentCampaigns = $campaigns->whereNull('parent_id');
            $subcampaignsByParent = $campaigns->whereNotNull('parent_id')->groupBy('parent_id');
        @endphp

        <div class="card-header">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Listado de transcripciones</h3>
                        @if($activeFilterCount > 0)
                            <span class="badge badge-neutral">{{ $activeFilterCount }} filtro(s)</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $interactions->total() }} registros encontrados</p>
                </div>
                <a href="{{ route('transcripts.create') }}" class="btn-primary btn-sm w-fit">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Cargar Nueva
                </a>
            </div>
        </div>

        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <form method="GET" action="{{ route('transcripts.index') }}" class="flex flex-wrap items-end gap-2 lg:flex-nowrap" x-data="{
                parentCampaignId: '{{ request('parent_campaign_id') }}',
                campaignId: '{{ request('campaign_id') }}',
                subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                get availableSubcampaigns() {
                    return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                }
            }">
                <div class="flex-1 min-w-0">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="search" name="search" id="search" value="{{ request('search') }}" class="form-input" placeholder="SN, ID o motivo">
                </div>

                <div class="flex-1 min-w-0 flex gap-2">
                    <div class="flex-1 min-w-0">
                        <label for="parent_campaign_id" class="form-label">Campaña</label>
                        <select name="parent_campaign_id" id="parent_campaign_id" x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                            <option value="">Todas</option>
                            @foreach($parentCampaigns as $pCamp)
                                <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label for="campaign_id" class="form-label">Subcampaña</label>
                        <select name="campaign_id" id="campaign_id" x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                            <option value="">Todas</option>
                            <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <label for="status" class="form-label">Estado</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="evaluated" {{ request('status') == 'evaluated' ? 'selected' : '' }}>Evaluada</option>
                    </select>
                </div>

                <div class="flex-1 min-w-0">
                    <label for="channel" class="form-label">Canal</label>
                    <select name="channel" id="channel" class="form-select">
                        <option value="">Todos</option>
                        @foreach($formOptions['channels'] as $value => $label)
                            <option value="{{ $value }}" {{ request('channel') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-0">
                    <label for="priority" class="form-label">Prioridad</label>
                    <select name="priority" id="priority" class="form-select">
                        <option value="">Todas</option>
                        @foreach($formOptions['priorities'] as $value => $label)
                            <option value="{{ $value }}" {{ request('priority') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-0">
                    <label for="uploaded_by" class="form-label">Cargado por</label>
                    <select name="uploaded_by" id="uploaded_by" class="form-select">
                        <option value="">Todos</option>
                        @foreach($uploaders as $uploader)
                            <option value="{{ $uploader->id }}" {{ request('uploaded_by') == $uploader->id ? 'selected' : '' }}>{{ $uploader->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2 shrink-0">
                    <button type="submit" class="btn-primary btn-md whitespace-nowrap">Filtrar</button>
                    <a href="{{ route('transcripts.index') }}" class="btn-secondary btn-md whitespace-nowrap">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="table">
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th class="w-56">Interacción</th>
                        <th>Asesor</th>
                        <th>Campaña</th>
                        <th>Subcampaña</th>
                        <th>Contexto</th>
                        <th>Fecha Llamada</th>
                        <th>Cargado por</th>
                        <th class="text-center w-32">Estado</th>
                        <th class="text-right w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody x-data="transcriptPoller({{ json_encode($interactions->pluck('id')) }})">
                    @forelse($interactions as $interaction)
                        @php
                            $interactionCampaign = $interaction->campaign;
                        @endphp
                        <tr>
                            <td>
                                <div class="space-y-1">
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
                                    <div class="font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ $interaction->call_sn ?: 'Sin SN' }}
                                    </div>
                                    @if($interaction->external_id)
                                        <div class="font-mono text-[11px] text-gray-400">{{ $interaction->external_id }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $interaction->agent?->full_name ?? '—' }}</span>
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $interactionCampaign?->parent?->name ?? $interactionCampaign?->name ?? '—' }}
                            </td>
                            <td>
                                @if($interactionCampaign?->parent)
                                    <span class="badge badge-info">{{ $interactionCampaign->name }}</span>
                                @else
                                    <span class="badge badge-warning">General</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if($interaction->channel)
                                        <span class="badge badge-neutral">{{ $formOptions['channels'][$interaction->channel] ?? $interaction->channel }}</span>
                                    @endif
                                    @if($interaction->priority)
                                        @php
                                            $priorityClass = match ($interaction->priority) {
                                                'critical', 'risk' => 'badge-danger',
                                                'high', 'complaint' => 'badge-warning',
                                                default => 'badge-neutral',
                                            };
                                        @endphp
                                        <span class="badge {{ $priorityClass }}">{{ $formOptions['priorities'][$interaction->priority] ?? $interaction->priority }}</span>
                                    @endif
                                </div>
                                @if($interaction->contact_reason)
                                    <div class="mt-1 max-w-56 truncate text-xs text-gray-500 dark:text-gray-400">{{ $interaction->contact_reason }}</div>
                                @endif
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $interaction->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">
                                {{ $interaction->uploadedBy->full_name ?? '—' }}
                            </td>
                            <td class="text-center" x-data="{ id: {{ $interaction->id }} }">
                                <span x-show="!statuses[id] && {{ $interaction->isTranscribing() ? 'true' : 'false' }}" class="badge badge-info">Transcribiendo</span>
                                <span x-show="!statuses[id] && {{ $interaction->isTranscriptionFailed() ? 'true' : 'false' }}" class="badge badge-danger">Error STT</span>
                                <span x-show="!statuses[id] && {{ $interaction->evaluation ? 'true' : 'false' }}" class="badge badge-success">Evaluada</span>
                                <span x-show="!statuses[id] && {{ !$interaction->isTranscribing() && !$interaction->isTranscriptionFailed() && !$interaction->evaluation ? 'true' : 'false' }}" class="badge badge-warning">Pendiente</span>
                                <span x-show="statuses[id]?.is_transcribing" class="badge badge-info">Transcribiendo</span>
                                <span x-show="statuses[id]?.is_failed" class="badge badge-danger">Error STT</span>
                                <span x-show="statuses[id]?.has_evaluation" class="badge badge-success">Evaluada</span>
                                <span x-show="statuses[id] && !statuses[id]?.is_transcribing && !statuses[id]?.is_failed && !statuses[id]?.has_evaluation" class="badge badge-warning">Pendiente</span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
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

                                    <a href="{{ route('transcripts.download', $interaction) }}"
                                        class="btn-ghost btn-sm text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                        title="Descargar Original">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                    </a>

                                    @if(!$interaction->evaluation)
                                        <form method="POST" action="{{ route('transcripts.evaluate', $interaction) }}"
                                            class="inline" x-data="{ submitting: false }" @submit="if(submitting) { $event.preventDefault(); } else { submitting = true; }">
                                            @csrf
                                            <button type="submit"
                                                class="btn-ghost btn-sm text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400"
                                                title="Evaluar con IA" :disabled="submitting" :class="{ 'opacity-50 cursor-not-allowed': submitting }">
                                                <svg x-show="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <svg x-show="submitting" class="w-4 h-4 animate-spin hidden" :class="{ 'hidden': !submitting, 'block': submitting }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('transcripts.edit', $interaction) }}"
                                        class="btn-ghost btn-sm text-indigo-600 hover:text-indigo-700" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

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
                            <td colspan="8">
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
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $interactions->links() }}
            </div>
        @endif
    </div>

    @if($shouldAutoRefreshTranscripts)
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transcriptPoller', (ids) => ({
                statuses: {},

                init() {
                    if (ids.length === 0 || typeof window.Echo === 'undefined') return;

                    window.Echo.private('interactions')
                        .listen('.interaction.status-changed', (e) => {
                            if (ids.includes(e.id)) {
                                this.statuses = { ...this.statuses, [e.id]: e };
                            }
                        });
                },

                destroy() {
                    if (typeof window.Echo !== 'undefined') {
                        window.Echo.leave('interactions');
                    }
                }
            }));
        });
    </script>
    @endif
</x-app-layout>
