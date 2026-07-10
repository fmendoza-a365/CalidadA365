<x-app-layout>
    <x-slot name="header">Evaluaciones</x-slot>

    @php
        $statusTone = function (string $status): string {
            return match ($status) {
                \App\Models\Evaluation::STATUS_PENDING_AI,
                \App\Models\Evaluation::STATUS_AI_PROCESSING,
                \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
                \App\Models\Evaluation::STATUS_PENDING_MONITOR_REVIEW => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20',
                \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
                \App\Models\Evaluation::STATUS_AGENT_ACCEPTED,
                \App\Models\Evaluation::STATUS_DISPUTE_RESOLVED => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
                \App\Models\Evaluation::STATUS_AGENT_DISPUTED,
                \App\Models\Evaluation::STATUS_AI_FAILED => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20',
                \App\Models\Evaluation::STATUS_CLOSED => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                default => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
            };
        };

        $scoreTone = function ($score): string {
            if ($score === null) {
                return 'text-gray-400 dark:text-gray-500';
            }

            return match (true) {
                (float) $score >= 90 => 'text-emerald-600 dark:text-emerald-400',
                (float) $score >= 80 => 'text-sky-600 dark:text-sky-400',
                (float) $score >= 70 => 'text-amber-600 dark:text-amber-400',
                default => 'text-rose-600 dark:text-rose-400',
            };
        };

        $scoreBar = function ($score): string {
            if ($score === null) {
                return 'bg-gray-300 dark:bg-gray-700';
            }

            return match (true) {
                (float) $score >= 90 => 'bg-emerald-500',
                (float) $score >= 80 => 'bg-sky-500',
                (float) $score >= 70 => 'bg-amber-500',
                default => 'bg-rose-500',
            };
        };

        $typeTone = fn (string $type): string => $type === 'ai'
            ? 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/20'
            : 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-500/10 dark:text-cyan-300 dark:border-cyan-500/20';

        $actionLabel = function (\App\Models\Evaluation $evaluation): string {
            return match ($evaluation->status) {
                \App\Models\Evaluation::STATUS_PENDING_AI,
                \App\Models\Evaluation::STATUS_AI_PROCESSING => 'Ver cola',
                \App\Models\Evaluation::STATUS_AI_FAILED => 'Revisar fallo',
                \App\Models\Evaluation::STATUS_PENDING_MONITOR_REVIEW,
                \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED => 'Revisar',
                \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'Seguimiento',
                \App\Models\Evaluation::STATUS_AGENT_DISPUTED => 'Resolver',
                \App\Models\Evaluation::STATUS_CLOSED => 'Auditar',
                default => 'Ver detalle',
            };
        };

        $activeFilterCount = collect(request()->only([
            'q',
            'campaign_id',
            'parent_campaign_id',
            'status',
            'type',
            'score_band',
            'start_date',
            'end_date',
        ]))->filter(fn ($value) => filled($value))->count();

        $autoRefreshStatuses = [
            \App\Models\Evaluation::STATUS_PENDING_AI,
            \App\Models\Evaluation::STATUS_AI_PROCESSING,
            \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
        ];
        $shouldAutoRefreshEvaluations = $evaluations->getCollection()->contains(
            fn ($evaluation) => in_array($evaluation->status, $autoRefreshStatuses, true)
                || in_array($evaluation->feedback_audio_status, ['pending', 'processing'], true)
        );

        // Helper para canal
        $channelData = function (?string $channel): array {
            $ch = strtolower($channel ?? '');
            return match($ch) {
                'phone', 'tel', 'voip', 'llamada' => [
                    'label' => 'Teléfono',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>',
                    'color' => 'text-indigo-500',
                ],
                'chat', 'whatsapp', 'sms' => [
                    'label' => 'Chat',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>',
                    'color' => 'text-emerald-500',
                ],
                'email', 'correo' => [
                    'label' => 'Email',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
                    'color' => 'text-blue-500',
                ],
                default => [
                    'label' => ucfirst($channel ?? 'N/A'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
                    'color' => 'text-gray-500',
                ],
            };
        };

        $directionData = function (?string $direction): array {
            $dir = strtolower($direction ?? '');
            return match($dir) {
                'inbound', 'incoming', 'entrante' => [
                    'label' => 'Entrante',
                    'icon' => '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" /></svg>',
                    'tone' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
                ],
                'outbound', 'outgoing', 'saliente' => [
                    'label' => 'Saliente',
                    'icon' => '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>',
                    'tone' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/20',
                ],
                default => [
                    'label' => ucfirst($direction ?? ''),
                    'icon' => '',
                    'tone' => '',
                ],
            };
        };

        $formatDuration = function (?int $seconds): string {
            if ($seconds === null) return null;
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes > 0 ? sprintf('%dm %ds', $minutes, $secs) : sprintf('%ds', $secs);
        };
    @endphp

    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
            @foreach([
                ['label' => 'Total', 'value' => $summary['total'], 'tone' => 'text-gray-900 dark:text-white'],
                ['label' => 'Promedio', 'value' => number_format($summary['avg_score'], 1).'%', 'tone' => 'text-gray-900 dark:text-white'],
                ['label' => 'Por revisar', 'value' => $summary['pending_review'], 'tone' => 'text-indigo-600 dark:text-indigo-400'],
                ['label' => 'Disputas', 'value' => $summary['disputed'], 'tone' => 'text-rose-600 dark:text-rose-400'],
                ['label' => 'Críticas', 'value' => $summary['critical'], 'tone' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Cerradas', 'value' => $summary['closed'], 'tone' => 'text-gray-900 dark:text-white'],
            ] as $metric)
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-[#141414]">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</div>
                    <div class="mt-1 text-2xl font-semibold {{ $metric['tone'] }}">{{ $metric['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Centro operativo de evaluaciones</h3>
                            @if($activeFilterCount > 0)
                                <span class="badge badge-neutral">{{ $activeFilterCount }} filtro(s)</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $evaluations->total() }} registros encontrados</p>
                    </div>
                    <a href="{{ route('exports.evaluations', request()->query()) }}" class="btn-secondary btn-sm w-fit">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar
                    </a>
                </div>
            </div>

            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <form method="GET" action="{{ route('evaluations.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1.2fr)_repeat(4,minmax(130px,1fr))_minmax(250px,1fr)_auto]">
                    <div class="md:col-span-2 xl:col-span-1">
                        <label for="q" class="form-label">Buscar</label>
                        <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-input" placeholder="Agente, campaña, monitor, SN o canal">
                    </div>

                    @php
                        $parentCampaigns = ($campaigns ?? collect())->whereNull('parent_id');
                        $subcampaignsByParent = ($campaigns ?? collect())->whereNotNull('parent_id')->groupBy('parent_id');
                    @endphp
                    <div class="grid grid-cols-2 gap-2 md:col-span-1" x-data="{
                        parentCampaignId: '{{ request('parent_campaign_id') }}',
                        campaignId: '{{ request('campaign_id') }}',
                        subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                        get availableSubcampaigns() {
                            return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                        }
                    }">
                        <div>
                            <label for="parent_campaign_id" class="form-label">Campaña</label>
                            <select name="parent_campaign_id" id="parent_campaign_id" x-model="parentCampaignId" @change="campaignId = ''" class="form-select">
                                <option value="">Todas</option>
                                @foreach($parentCampaigns as $pCamp)
                                    <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="campaign_id" class="form-label">Subcampaña</label>
                            <select name="campaign_id" id="campaign_id" x-model="campaignId" :disabled="!parentCampaignId || availableSubcampaigns.length === 0" class="form-select disabled:opacity-50">
                                <option value="">Todas</option>
                                <template x-for="sub in availableSubcampaigns" :key="sub.id">
                                    <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="status" class="form-label">Estado</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ \App\Models\Evaluation::statusLabel($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="type" class="form-label">Tipo</label>
                        <select name="type" id="type" class="form-select">
                            <option value="">Todos</option>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="score_band" class="form-label">Score</label>
                        <select name="score_band" id="score_band" class="form-select">
                            <option value="">Todos</option>
                            <option value="excellent" {{ request('score_band') === 'excellent' ? 'selected' : '' }}>90%+</option>
                            <option value="good" {{ request('score_band') === 'good' ? 'selected' : '' }}>80% - 89%</option>
                            <option value="watch" {{ request('score_band') === 'watch' ? 'selected' : '' }}>70% - 79%</option>
                            <option value="critical" {{ request('score_band') === 'critical' ? 'selected' : '' }}>< 70%</option>
                            <option value="unscored" {{ request('score_band') === 'unscored' ? 'selected' : '' }}>Sin score</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:col-span-2 xl:col-span-1">
                        <div>
                            <label for="start_date" class="form-label">Desde</label>
                            <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" class="form-input">
                        </div>
                        <div>
                            <label for="end_date" class="form-label">Hasta</label>
                            <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" class="form-input">
                        </div>
                    </div>

                    <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                        <button type="submit" class="btn-primary btn-md">Filtrar</button>
                        <a href="{{ route('evaluations.index') }}" class="btn-secondary btn-md">Limpiar</a>
                    </div>
                </form>
            </div>

            {{-- Vista de escritorio --}}
            <div class="hidden overflow-x-auto xl:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th class="min-w-[150px]">Agente</th>
                            <th class="w-16 text-center">Tipo</th>
                            <th class="min-w-[140px]">Campaña</th>
                            <th class="min-w-[130px]">Subcampaña</th>
                            <th class="w-28">Score</th>
                            <th class="w-36">Canal</th>
                            <th class="w-10 text-center">Gold</th>
                            <th class="w-32">Fecha Llamada</th>
                            <th class="w-32">Fecha Subida</th>
                            <th class="w-40">Estado</th>
                            <th class="w-36">Responsable</th>
                            <th class="w-24 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluations as $evaluation)
                            @php
                                $score = $evaluation->percentage_score;
                                $hasScore = $score !== null;
                                $scoreWidth = $hasScore ? max(0, min(100, (float) $score)) : 0;
                                $interaction = $evaluation->interaction;
                                $evaluationCampaign = $evaluation->campaign;
                                $uploadedAt = $interaction?->uploaded_at ?? $interaction?->created_at ?? $evaluation->created_at;
                                $ch = $channelData($interaction->channel ?? null);
                                $dir = $directionData($interaction->direction ?? null);
                            @endphp
                            <tr>
                                {{-- Columna ID --}}
                                <td>
                                    <div class="flex h-6 w-6 items-center justify-center rounded bg-gray-100 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $evaluation->id }}
                                    </div>
                                </td>

                                {{-- Columna Agente --}}
                                <td>
                                    <div class="flex items-center gap-1">
                                        <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $evaluation->agent?->full_name ?? 'Sin asesor' }}</span>
                                        @if($evaluation->agent_viewed_at)
                                            <span class="inline-flex items-center text-emerald-600 dark:text-emerald-400" title="Visto por asesor el {{ $evaluation->agent_viewed_at->format('d/m/Y H:i') }}">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            </span>
                                        @endif
                                    </div>
                                    @if($evaluation->ai_provider)
                                        <div class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500 truncate max-w-[120px]" title="{{ $evaluation->ai_provider }} / {{ $evaluation->ai_model }}">
                                            {{ strtoupper($evaluation->ai_provider) }}{{ $evaluation->ai_model ? ' / '.$evaluation->ai_model : '' }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Columna Tipo --}}
                                <td class="text-center">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold leading-tight {{ $typeTone($evaluation->type) }}">
                                        {{ $evaluation->type === 'ai' ? 'IA' : 'Manual' }}
                                    </span>
                                </td>

                                {{-- Columna Campaña --}}
                                <td>
                                    <div class="text-xs text-gray-700 dark:text-gray-300 truncate max-w-[140px]" title="{{ $evaluationCampaign?->parent?->name ?? $evaluationCampaign?->name }}">
                                        {{ $evaluationCampaign?->parent?->name ?? $evaluationCampaign?->name ?? 'Sin campaña' }}
                                    </div>
                                </td>

                                {{-- Columna Subcampaña --}}
                                <td>
                                    @if($evaluationCampaign?->parent)
                                        <span class="badge badge-info">{{ $evaluationCampaign->name }}</span>
                                    @else
                                        <span class="badge badge-warning">General</span>
                                    @endif
                                </td>

                                {{-- Columna Score --}}
                                <td>
                                    <div class="w-24">
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-base font-bold {{ $scoreTone($score) }}">{{ $hasScore ? number_format((float) $score, 1).'%' : '--' }}</span>
                                            <span class="text-[9px] text-gray-400">{{ $evaluation->total_score !== null ? number_format((float) $evaluation->total_score, 0) : '--' }}/{{ number_format((float) $evaluation->max_possible_score, 0) }}</span>
                                        </div>
                                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-full rounded-full {{ $scoreBar($score) }}" style="width: {{ $scoreWidth }}%"></div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Columna Canal --}}
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <span class="{{ $ch['color'] }}">{!! $ch['icon'] !!}</span>
                                        <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $ch['label'] }}</span>
                                        @if($dir['label'])
                                            <span class="inline-flex items-center gap-0.5 rounded border px-1 py-0.5 text-[8px] font-semibold {{ $dir['tone'] }}">
                                                {!! $dir['icon'] !!}{{ $dir['label'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($interaction->audio_duration)
                                        <div class="mt-0.5 flex items-center gap-1 text-[9px] text-gray-400 dark:text-gray-500">
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ $formatDuration($interaction->audio_duration) }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Columna Gold --}}
                                <td class="text-center">
                                    @if($evaluation->is_gold)
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                                            <svg class="w-2.5 h-2.5 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                            Gold
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>

                                {{-- Columna Fecha Llamada --}}
                                <td>
                                    <div class="text-[11px] text-gray-700 dark:text-gray-300">{{ $interaction?->occurred_at?->format('d/m/Y') ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $interaction?->occurred_at?->format('H:i') ?? '' }}</div>
                                </td>

                                {{-- Columna Fecha Subida --}}
                                <td>
                                    <div class="text-[11px] text-gray-700 dark:text-gray-300">{{ $uploadedAt?->format('d/m/Y') ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $uploadedAt?->format('H:i') ?? '' }}</div>
                                    <div class="text-[9px] text-gray-400 dark:text-gray-500">{{ $uploadedAt?->diffForHumans() ?? '' }}</div>
                                </td>

                                {{-- Columna Estado --}}
                                <td>
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $statusTone($evaluation->status) }}">
                                        {{ \App\Models\Evaluation::statusLabel($evaluation->status) }}
                                    </span>
                                </td>

                                {{-- Columna Responsable --}}
                                <td>
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ $evaluation->evaluator?->full_name ?? $evaluation->reviewer?->full_name ?? 'Pendiente' }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[9px] text-gray-400 dark:text-gray-500">
                                        @if($evaluation->visible_to_agent_at)
                                            <span class="flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                {{ $evaluation->visible_to_agent_at->format('d/m/Y') }}
                                            </span>
                                        @else
                                            Sin publicar
                                        @endif
                                    </div>
                                </td>

                                {{-- Columna Acción --}}
                                <td class="text-right">
                                    <a href="{{ route('evaluations.show', $evaluation) }}" class="btn-secondary btn-sm text-[11px]">
                                        {{ $actionLabel($evaluation) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">
                                    <div class="empty-state py-12">
                                        <div class="empty-state-icon">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        </div>
                                        <p class="font-medium text-gray-900 dark:text-white">No hay evaluaciones con estos filtros.</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta la búsqueda o limpia los filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Vista móvil --}}
            <div class="space-y-3 p-4 xl:hidden">
                @forelse($evaluations as $evaluation)
                    @php
                        $score = $evaluation->percentage_score;
                        $hasScore = $score !== null;
                        $scoreWidth = $hasScore ? max(0, min(100, (float) $score)) : 0;
                        $interaction = $evaluation->interaction;
                        $evaluationCampaign = $evaluation->campaign;
                        $uploadedAt = $interaction?->uploaded_at ?? $interaction?->created_at ?? $evaluation->created_at;
                        $ch = $channelData($interaction->channel ?? null);
                    @endphp
                    <a href="{{ route('evaluations.show', $evaluation) }}" class="block rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $evaluation->agent?->full_name ?? 'Sin asesor' }}</span>
                                    <span class="rounded-full border px-1.5 py-0.5 text-[10px] font-semibold {{ $typeTone($evaluation->type) }}">{{ $evaluation->type === 'ai' ? 'IA' : 'MNL' }}</span>
                                    @if($evaluation->agent_viewed_at)
                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $evaluationCampaign?->parent?->name ?? $evaluationCampaign?->name ?? 'Sin campaña' }}
                                    @if($evaluationCampaign?->parent)
                                        <span class="text-gray-400">/</span> {{ $evaluationCampaign->name }}
                                    @endif
                                </div>
                                @if($interaction)
                                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                        <span class="flex items-center gap-1">
                                            <span class="{{ $ch['color'] }}">{!! $ch['icon'] !!}</span>
                                            {{ $ch['label'] }}
                                        </span>
                                        @if($interaction->contact_reason)
                                            <span class="truncate max-w-[150px]">· {{ Str::limit($interaction->contact_reason, 20) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-2xl font-bold {{ $scoreTone($score) }}">{{ $hasScore ? number_format((float) $score, 1).'%' : '--' }}</div>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full rounded-full {{ $scoreBar($score) }}" style="width: {{ $scoreWidth }}%"></div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $statusTone($evaluation->status) }}">
                                {{ \App\Models\Evaluation::statusLabel($evaluation->status) }}
                            </span>
                            <span class="text-[10px] text-gray-400">Llamada: {{ $interaction?->occurred_at?->format('d/m/Y H:i') ?? 'N/A' }}</span>
                            <span class="text-[10px] text-gray-400">Subida: {{ $uploadedAt?->format('d/m/Y H:i') ?? 'N/A' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-gray-900">
                        <p class="font-medium text-gray-900 dark:text-white">No hay evaluaciones con estos filtros.</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajusta la búsqueda o limpia los filtros.</p>
                    </div>
                @endforelse
            </div>

            @if($evaluations->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $evaluations->links() }}
                </div>
            @endif
        </div>
    </div>

    @if($shouldAutoRefreshEvaluations)
        @include('partials.auto-refresh')
    @endif
</x-app-layout>
