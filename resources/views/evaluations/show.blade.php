<x-app-layout>
    <x-slot name="header">Evaluación - {{ $evaluation->agent->full_name }}</x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @php
            $score = (float) $evaluation->percentage_score;
            $scoreClass = match (true) {
                $score >= 90 => 'score-excellent',
                $score >= 80 => 'score-good',
                $score >= 70 => 'score-average',
                default => 'score-poor',
            };
            $isCorrectedFinal = $evaluation->type === 'manual' && $evaluation->interaction?->aiEvaluation;
            $autoRefreshStatuses = [
                \App\Models\Evaluation::STATUS_PENDING_AI,
                \App\Models\Evaluation::STATUS_AI_PROCESSING,
                \App\Models\Evaluation::STATUS_AI_REANALYSIS_REQUESTED,
            ];
            $shouldAutoRefreshEvaluation = in_array($evaluation->status, $autoRefreshStatuses, true)
                || in_array($evaluation->feedback_audio_status, ['pending', 'processing'], true);
            $statusBadgeClass = match ($evaluation->status) {
                \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT => 'badge-warning',
                \App\Models\Evaluation::STATUS_AGENT_ACCEPTED,
                \App\Models\Evaluation::STATUS_DISPUTE_RESOLVED => 'badge-success',
                \App\Models\Evaluation::STATUS_AGENT_DISPUTED,
                \App\Models\Evaluation::STATUS_AI_FAILED => 'badge-danger',
                default => $evaluation->isPendingMonitorReview() ? 'badge-warning' : 'badge-neutral',
            };
            $evaluationCampaign = $evaluation->campaign;
        @endphp

        <div class="card">
            <div class="p-4 lg:p-5">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                            <div class="lg:w-40">
                                <div class="text-3xl font-bold leading-none {{ $scoreClass }}">{{ number_format($score, 1) }}%</div>
                                <div class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Puntaje final</div>
                            </div>

                            <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                                <div>
                                    <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Evaluación</div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $isCorrectedFinal ? 'Final corregida' : 'Evaluación IA' }}
                                    </p>
                                    @if($evaluation->evaluator)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Monitor: {{ $evaluation->evaluator->full_name }}</p>
                                    @endif
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Campaña</div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $evaluationCampaign?->parent?->name ?? $evaluationCampaign?->name ?? 'Sin campaña' }}</p>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Subcampaña</div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $evaluationCampaign?->parent ? $evaluationCampaign->name : 'General' }}</p>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Interacción</div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $evaluation->interaction?->occurred_at?->format('d/m/Y H:i') ?? $evaluation->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado</div>
                                    <span class="badge {{ $statusBadgeClass }}">{{ \App\Models\Evaluation::statusLabel($evaluation->status) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 xl:w-[360px] xl:grid-cols-1">
                        <a href="{{ route('transcripts.show', $evaluation->interaction) }}"
                            class="btn-secondary btn-sm w-full justify-center text-center">
                            Ver Transcripción
                        </a>

                        @if ($evaluation->type === 'ai' && ! $evaluation->interaction->manualEvaluation)
                            @can('publish', $evaluation)
                                @if($evaluation->isReviewClaimedByOther(auth()->user()) && ! auth()->user()->hasRole('admin'))
                                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200">
                                        Reservado por {{ $evaluation->reviewClaimer?->name ?? 'otro monitor' }}
                                        @if($evaluation->review_claim_expires_at)
                                            durante {{ $evaluation->review_claim_expires_at->diffForHumans(null, true) }} más
                                        @endif
                                    </div>
                                @else
                                    <a href="{{ route('evaluations.create_manual', $evaluation->interaction) }}"
                                        class="btn-primary btn-sm w-full justify-center">
                                        {{ $evaluation->isReviewClaimedBy(auth()->user()) ? 'Continuar Corrección' : 'Corregir Manualmente' }}
                                    </a>
                                @endif
                            @endcan
                        @endif

                        @can('publish', $evaluation)
                            <form method="POST" action="{{ route('evaluations.publish', $evaluation) }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm w-full justify-center">
                                    Aprobar y Publicar
                                </button>
                            </form>
                        @endcan

                        @can('reanalyze', $evaluation)
                            <form method="POST" action="{{ route('evaluations.reanalyze', $evaluation) }}">
                                @csrf
                                <button type="submit" class="btn-secondary btn-sm w-full justify-center">
                                    Reanalizar IA
                                </button>
                            </form>
                        @endcan

                        @can('close', $evaluation)
                            <form method="POST" action="{{ route('evaluations.close', $evaluation) }}" class="grid gap-2 sm:col-span-2 xl:col-span-1">
                                @csrf
                                <input type="text" name="closure_reason" class="form-input text-xs"
                                    placeholder="Motivo de cierre (opcional)">
                                <button type="submit" class="btn-secondary btn-sm w-full justify-center">
                                    Cerrar Evaluación
                                </button>
                            </form>
                        @endcan

                        @can('reopen', $evaluation)
                            <form method="POST" action="{{ route('evaluations.reopen', $evaluation) }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm w-full justify-center">
                                    Reabrir Evaluación
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @if($interactionAudioUrl)
            @include('transcripts.partials.audio-player-simple', [
                'audioUrl' => $interactionAudioUrl,
                'title' => 'Audio de la interacción',
                'subtitle' => 'Grabación original cargada para esta evaluación.',
                'fileName' => $evaluation->interaction?->file_name,
                'durationLabel' => null,
                'bars' => $audioTimeline['bars'] ?? null,
            ])
        @endif

        @if($evaluation->isClosed())
            <div class="alert alert-info">
                <div>
                    <strong>Evaluación cerrada</strong>
                    <p class="mt-1 text-sm">
                        Cerrada por {{ $evaluation->closer?->full_name ?? 'N/A' }}
                        @if($evaluation->closed_at)
                            el {{ $evaluation->closed_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    @if($evaluation->closure_reason)
                        <p class="mt-1 text-sm">{{ $evaluation->closure_reason }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if($calibrationComparison)
            @php
                $delta = $calibrationComparison['score_delta'];
                $absoluteDelta = $calibrationComparison['absolute_score_delta'];
                $agreement = $calibrationComparison['item_agreement_rate'];
                $deltaClass = $absoluteDelta <= 5
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : ($absoluteDelta <= 10 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400');
                $statusLabels = [
                    'compliant' => 'Cumple',
                    'non_compliant' => 'No cumple',
                    'not_found' => 'No encontrado',
                ];
                $mismatches = collect($calibrationComparison['criteria'])
                    ->filter(fn ($criterion) => $criterion['comparable'] && ! $criterion['matches']);
            @endphp
            <details class="card border-sky-100 dark:border-sky-500/30">
                <summary class="card-header cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Calibración IA vs Monitor</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $calibrationComparison['item_matches_count'] }} de {{ $calibrationComparison['item_compared_count'] }} criterios alineados.
                                {{ $mismatches->count() }} diferencia(s) detectada(s).
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="rounded-lg border border-gray-200 px-3 py-1.5 dark:border-gray-700">
                                IA {{ number_format($calibrationComparison['ai_score'], 1) }}%
                            </span>
                            <span class="rounded-lg border border-gray-200 px-3 py-1.5 dark:border-gray-700">
                                Monitor {{ number_format($calibrationComparison['manual_score'], 1) }}%
                            </span>
                            <span class="rounded-lg border border-gray-200 px-3 py-1.5 font-semibold {{ $deltaClass }} dark:border-gray-700">
                                {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} pp
                            </span>
                            <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                Mostrar detalle
                            </span>
                        </div>
                    </div>
                </summary>
                <div class="card-body space-y-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Puntaje IA</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($calibrationComparison['ai_score'], 1) }}%</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Puntaje Monitor</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($calibrationComparison['manual_score'], 1) }}%</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Diferencia</div>
                            <div class="text-xl font-bold {{ $deltaClass }}">
                                {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} pp
                            </div>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Acuerdo por criterio</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ $agreement === null ? 'N/A' : number_format($agreement, 1).'%' }}
                            </div>
                        </div>
                    </div>

                    @if($mismatches->isNotEmpty())
                        <div class="max-h-96 overflow-auto rounded-lg border border-gray-200 dark:border-gray-800">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white dark:bg-[#141414]">
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Criterio con diferencia</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IA</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Monitor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mismatches as $criterion)
                                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $criterion['criterion'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $statusLabels[$criterion['ai_status']] ?? 'Sin dato' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $statusLabels[$criterion['manual_status']] ?? 'Sin dato' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-3 text-sm text-emerald-700 dark:text-emerald-300">
                            La IA y el monitor están alineados en los criterios comparables.
                        </div>
                    @endif
                </div>
            </details>
        @endif

        @if($evaluation->isPendingMonitorReview() && auth()->user()->cannot('respond', $evaluation))
            <div class="card border-indigo-100 dark:border-indigo-500/30">
                <div class="card-body">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Revisión del monitor pendiente</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Esta evaluación fue generada por IA y aún no es visible para el asesor. Las acciones disponibles están en el encabezado de la evaluación.
                        </p>
                        @if($evaluation->reanalysis_requested_at)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                Reanálisis solicitado el {{ $evaluation->reanalysis_requested_at->format('d/m/Y H:i') }}.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- AI Feedback Card -->
        @if($evaluation->ai_summary || $evaluation->ai_feedback)
            <div
                class="card bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-900/20 dark:to-gray-800 border-indigo-100 dark:border-indigo-500/30">
                <div class="card-header border-b border-indigo-100 dark:border-indigo-500/30">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Feedback de evaluación
                        </h3>
                    </div>
                    @if($evaluation->type === 'ai' && ($evaluation->ai_provider || $evaluation->ai_model || $evaluation->ai_prompt_version))
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Proveedor: {{ $evaluation->ai_provider ?? 'N/A' }}
                            <span class="mx-1">|</span>
                            Modelo: {{ $evaluation->ai_model ?? 'N/A' }}
                            <span class="mx-1">|</span>
                            Prompt: {{ $evaluation->ai_prompt_version ?? 'N/A' }}
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @php
                        $sections = $evaluation->structuredAiFeedback();
                    @endphp

                    @if($feedbackAudioUrl)
                        <div class="mb-4">
                            @include('transcripts.partials.audio-player-simple', [
                                'audioUrl' => $feedbackAudioUrl,
                                'title' => 'Feedback por voz',
                                'subtitle' => 'Resumen narrado generado al publicar la evaluación.',
                                'fileName' => $evaluation->feedback_audio_path ? basename($evaluation->feedback_audio_path) : null,
                                'durationLabel' => null,
                            ])
                        </div>
                    @elseif($evaluation->feedback_audio_status === 'processing' || $evaluation->feedback_audio_status === 'pending')
                        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            El audio de feedback se está generando.
                        </div>
                    @endif

                    <div class="space-y-3">
                        @foreach($sections as $index => $section)
                            <div x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" 
                                 class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800 shadow-sm hover:shadow transition-all duration-200">
                                <button @click="open = !open" 
                                        class="w-full flex items-center justify-between p-4 text-left bg-gray-50/50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <h4 class="font-bold text-gray-800 dark:text-gray-100 text-base flex items-center gap-2">
                                        {{ $section['title'] }}
                                    </h4>
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white dark:bg-gray-700 shadow-sm border border-gray-100 dark:border-gray-600 transition-transform duration-200"
                                         :class="{'rotate-180': open}">
                                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </button>
                                <div x-show="open" 
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="border-t border-gray-100 dark:border-gray-700/50">
                                    <div class="p-5 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                         {!! nl2br(e($section['content'])) !!}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Items de Evaluación -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-white">Resultados por Criterio</h3>
            </div>
            <div class="card-body space-y-6">
                @php
                    $statusLabels = [
                        'compliant' => 'Cumple',
                        'non_compliant' => 'No cumple',
                        'not_found' => 'No encontrado',
                    ];
                    $statusClasses = [
                        'compliant' => 'badge-success',
                        'non_compliant' => 'badge-danger',
                        'not_found' => 'badge-warning',
                    ];
                    $counterpartEvaluation = $evaluation->type === 'manual'
                        ? $evaluation->interaction?->aiEvaluation
                        : null;
                @endphp

                @foreach($evaluation->formVersion->formAttributes as $attribute)
                    <div class="border-b border-gray-100 dark:border-gray-800 pb-6 last:border-0 last:pb-0">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-4">
                            {{ $attribute->name }}
                            <span class="text-indigo-600 dark:text-gray-400">({{ $attribute->weight }}%)</span>
                        </h4>

                        <div class="space-y-3">
                            @foreach($attribute->subAttributes as $subAttribute)
                                @php
                                    $item = $evaluation->items->firstWhere('subattribute_id', $subAttribute->id);
                                    $referenceItem = $counterpartEvaluation?->items?->firstWhere('subattribute_id', $subAttribute->id);
                                    $evidenceQuote = $item?->evidence_quote ?: $referenceItem?->evidence_quote;
                                    $evidenceReference = $item?->evidence_reference ?: $referenceItem?->evidence_reference;
                                    $primaryNotes = $item?->ai_notes;
                                    $referenceNotes = $referenceItem?->ai_notes;
                                    $isChanged = $item && $referenceItem && $item->status !== $referenceItem->status;
                                    $showReferenceNotes = $referenceNotes && $referenceNotes !== $primaryNotes;
                                @endphp

                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                                    <div class="flex justify-between items-start gap-4 mb-2">
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $subAttribute->name }}
                                                </div>
                                                @if($isChanged)
                                                    <span class="badge badge-info">Corregido</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Peso:
                                                {{ $subAttribute->weight_percent }}%</div>
                                        </div>
                                        <div class="text-right">
                                            @if($item)
                                                @if($item->status === 'compliant')
                                                    <span class="badge badge-success inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Cumple</span>
                                                @elseif($item->status === 'non_compliant')
                                                    <span class="badge badge-danger inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> No Cumple</span>
                                                @elseif($item->status === 'not_found')
                                                    <span class="badge badge-warning">? No Encontrado</span>
                                                @else
                                                    <span class="badge badge-neutral">N/A</span>
                                                @endif

                                                @if($item->confidence)
                                                    <div class="text-xs text-gray-500 mt-1">Confianza:
                                                        {{ number_format($item->confidence * 100, 0) }}%</div>
                                                @endif

                                                @if($referenceItem)
                                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        IA: {{ $statusLabels[$referenceItem->status] ?? 'Sin dato' }}
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if($evidenceQuote)
                                        <div
                                            class="mt-3 bg-white dark:bg-gray-900 border-l-4 border-indigo-500 dark:border-gray-600 p-3 rounded-r-lg">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Evidencia:</div>
                                            <div class="text-sm text-gray-700 dark:text-gray-300 italic">
                                                "{{ $evidenceQuote }}"</div>
                                            @if($evidenceReference)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Referencia:
                                                    {{ $evidenceReference }}</div>
                                            @endif
                                        </div>
                                    @endif
                                    @php
                                        $displayNotes = trim($primaryNotes ?: '');
                                        if (!$displayNotes) {
                                            $displayNotes = match($item?->status) {
                                                'compliant' => 'Cumple con los criterios establecidos.',
                                                'non_compliant' => 'No cumple con el protocolo de calidad.',
                                                'not_found' => 'Criterio no identificado o no aplica en la interacción.',
                                                default => 'Sin observaciones registradas.'
                                            };
                                        }
                                    @endphp
                                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        <strong>{{ $evaluation->type === 'manual' ? 'Nota del monitor:' : 'Notas:' }}</strong> {{ $displayNotes }}
                                    </div>

                                    @if($showReferenceNotes)
                                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-500">
                                            <strong>Nota IA original:</strong> {{ $referenceNotes }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Respuesta del Asesor -->
        @if($evaluation->agentResponse)
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Respuesta del Asesor</h3>
                </div>
                <div class="card-body">
                    @if($evaluation->agentResponse->response_type === 'accept')
                        <div class="alert alert-success">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <strong>Aceptado</strong>
                                @if($evaluation->agentResponse->commitment_comment)
                                    <p class="mt-1">Compromiso: {{ $evaluation->agentResponse->commitment_comment }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <strong>Disputa</strong>
                                <p class="mt-1">Motivo: {{ $evaluation->agentResponse->dispute_reason }}</p>
                            </div>
                        </div>
                    @endif
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                        Respondido el {{ $evaluation->agentResponse->responded_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>
        @elseif(auth()->user()->id === $evaluation->agent_id && $evaluation->status === \App\Models\Evaluation::STATUS_PUBLISHED_TO_AGENT)
            <!-- Formulario de Respuesta -->
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Tu Respuesta</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('evaluations.respond', $evaluation) }}"
                        x-data="{ responseType: 'accept' }" class="form-section">
                        @csrf

                        <div class="form-group">
                            <label class="form-label">Tipo de Respuesta</label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="response_type" value="accept" x-model="responseType"
                                        class="form-checkbox">
                                    <span class="text-gray-700 dark:text-gray-300">Aceptar evaluación y comprometerme a
                                        mejorar</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="response_type" value="dispute" x-model="responseType"
                                        class="form-checkbox">
                                    <span class="text-gray-700 dark:text-gray-300">Refutar/Apelar evaluación</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="responseType === 'accept'" class="form-group">
                            <label for="commitment_comment" class="form-label">Compromiso de Mejora</label>
                            <textarea name="commitment_comment" id="commitment_comment" rows="3" class="form-textarea"
                                placeholder="Describe cómo planeas mejorar en los puntos señalados..."></textarea>
                        </div>

                        <div x-show="responseType === 'dispute'" class="form-group">
                            <label for="dispute_reason" class="form-label">Motivo de la Disputa <span
                                    class="text-rose-500">*</span></label>
                            <textarea name="dispute_reason" id="dispute_reason" rows="3" class="form-textarea"
                                placeholder="Explica por qué no estás de acuerdo con la evaluación..."></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary btn-md">Enviar Respuesta</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Flujo de Disputa -->
        @if($evaluation->dispute)
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Flujo de Disputa</h3>
                        <span class="badge {{ $evaluation->dispute->isResolved() ? 'badge-success' : 'badge-warning' }}">
                            {{ \App\Models\DisputeResolution::statusLabel($evaluation->dispute->status) }}
                        </span>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">1. Supervisor</div>
                            @if($evaluation->dispute->supervisor_notes)
                                <div class="font-medium text-gray-900 dark:text-white">{{ $evaluation->dispute->supervisorReviewer->name ?? 'Supervisor' }}</div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $evaluation->dispute->supervisor_notes }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $evaluation->dispute->supervisor_reviewed_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pendiente o no requerido.</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">2. QA Monitor</div>
                            @if($evaluation->dispute->qa_notes)
                                <div class="font-medium text-gray-900 dark:text-white">{{ $evaluation->dispute->qaReviewer->name ?? 'QA' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Recomendación: {{ ucfirst(str_replace('_', ' ', $evaluation->dispute->qa_recommendation)) }}</div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $evaluation->dispute->qa_notes }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $evaluation->dispute->qa_reviewed_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pendiente.</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">3. Coordinador QA</div>
                            @if($evaluation->dispute->coordinator_notes)
                                <div class="font-medium text-gray-900 dark:text-white">{{ $evaluation->dispute->coordinatorReviewer->name ?? 'Coordinador' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Decisión: {{ ucfirst(str_replace('_', ' ', $evaluation->dispute->coordinator_decision)) }}</div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $evaluation->dispute->coordinator_notes }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $evaluation->dispute->coordinator_reviewed_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pendiente.</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">4. QA Manager</div>
                            @if($evaluation->dispute->resolved_at)
                                <div class="font-medium text-gray-900 dark:text-white">{{ $evaluation->dispute->resolvedBy->name ?? 'QA Manager' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Resolución: {{ ucfirst(str_replace('_', ' ', $evaluation->dispute->resolution_decision)) }}</div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $evaluation->dispute->resolution_notes }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $evaluation->dispute->resolved_at?->format('d/m/Y H:i') }}</div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pendiente resolución final.</p>
                            @endif
                        </div>
                    </div>

                    @can('supervisorReview', $evaluation->dispute)
                        @if(!$evaluation->dispute->supervisor_notes)
                            <form method="POST" action="{{ route('disputes.supervisor-review', $evaluation->dispute) }}" class="form-section border-t border-gray-100 dark:border-gray-800 pt-4">
                                @csrf
                                <div class="form-group">
                                    <label for="supervisor_notes" class="form-label">Comentario operativo del supervisor <span class="text-rose-500">*</span></label>
                                    <textarea name="supervisor_notes" id="supervisor_notes" rows="3" class="form-textarea" required></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary btn-md">Enviar a QA</button>
                                </div>
                            </form>
                        @endif
                    @endcan

                    @can('qaReview', $evaluation->dispute)
                        @if(!$evaluation->dispute->qa_notes && !$evaluation->dispute->isResolved())
                            <form method="POST" action="{{ route('disputes.qa-review', $evaluation->dispute) }}" class="form-section border-t border-gray-100 dark:border-gray-800 pt-4">
                                @csrf
                                <div class="form-group">
                                    <label for="qa_recommendation" class="form-label">Recomendación QA <span class="text-rose-500">*</span></label>
                                    <select name="qa_recommendation" id="qa_recommendation" class="form-select" required>
                                        <option value="upheld">Mantener evaluación original</option>
                                        <option value="overturned">Corregir a favor del asesor</option>
                                        <option value="partial">Ajuste parcial</option>
                                        <option value="needs_manager">Escalar a QA Manager</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="qa_notes" class="form-label">Análisis del monitor QA <span class="text-rose-500">*</span></label>
                                    <textarea name="qa_notes" id="qa_notes" rows="3" class="form-textarea" required></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary btn-md">Enviar a Coordinador</button>
                                </div>
                            </form>
                        @endif
                    @endcan

                    @can('coordinatorReview', $evaluation->dispute)
                        @if($evaluation->dispute->qa_notes && !$evaluation->dispute->coordinator_notes && !$evaluation->dispute->isResolved())
                            <form method="POST" action="{{ route('disputes.coordinator-review', $evaluation->dispute) }}" class="form-section border-t border-gray-100 dark:border-gray-800 pt-4">
                                @csrf
                                <div class="form-group">
                                    <label for="coordinator_decision" class="form-label">Validación del coordinador <span class="text-rose-500">*</span></label>
                                    <select name="coordinator_decision" id="coordinator_decision" class="form-select" required>
                                        <option value="validated">Validar recomendación QA</option>
                                        <option value="needs_adjustment">Requiere ajuste</option>
                                        <option value="escalate_manager">Escalar a QA Manager</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="coordinator_notes" class="form-label">Notas del coordinador <span class="text-rose-500">*</span></label>
                                    <textarea name="coordinator_notes" id="coordinator_notes" rows="3" class="form-textarea" required></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary btn-md">Dejar lista para resolución</button>
                                </div>
                            </form>
                        @endif
                    @endcan

                    @can('resolve', $evaluation->dispute)
                    @if(!$evaluation->dispute->isResolved())
                    <form method="POST" action="{{ route('disputes.resolve', $evaluation->dispute) }}" class="form-section">
                        @csrf

                        <div class="form-group">
                            <label for="resolution_decision" class="form-label">Decisión <span
                                    class="text-rose-500">*</span></label>
                            <select name="resolution_decision" id="resolution_decision" class="form-select" required>
                                <option value="upheld">Mantener evaluación original</option>
                                <option value="overturned">Anular evaluación</option>
                                <option value="partial">Ajuste parcial</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="resolution_notes" class="form-label">Notas de Resolución <span
                                    class="text-rose-500">*</span></label>
                            <textarea name="resolution_notes" id="resolution_notes" rows="3" class="form-textarea"
                                required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="adjusted_score" class="form-label">Puntaje Ajustado (opcional)</label>
                            <input type="number" name="adjusted_score" id="adjusted_score" step="0.01" min="0" max="100"
                                class="form-input">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary btn-md">Resolver Disputa</button>
                        </div>
                    </form>
                    @endif
                    @endcan
                </div>
            </div>
        @endif

        @if(auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor']) && $evaluation->auditEvents->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Bitácora de Evaluación</h3>
                        <a href="{{ route('exports.evaluation-audit', $evaluation) }}" class="btn-secondary btn-sm">Exportar CSV</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($evaluation->auditEvents as $event)
                            <div class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ \App\Models\EvaluationAuditEvent::eventLabel($event->event) }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $event->actor?->name ?? 'Sistema' }}
                                            @if($event->from_status || $event->to_status)
                                                <span class="mx-1">-</span>
                                                {{ $event->from_status ? \App\Models\Evaluation::statusLabel($event->from_status) : 'Inicio' }}
                                                ->
                                                {{ $event->to_status ? \App\Models\Evaluation::statusLabel($event->to_status) : 'Sin cambio' }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 sm:text-right">
                                        {{ $event->occurred_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div>
            <a href="{{ route('evaluations.index') }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Listado
            </a>
        </div>
    </div>

    @if($shouldAutoRefreshEvaluation)
        @include('partials.auto-refresh')
    @endif
</x-app-layout>
