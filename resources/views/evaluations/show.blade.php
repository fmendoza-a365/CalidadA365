<x-app-layout>
    <x-slot name="header">Evaluación - {{ $evaluation->agent->name }}</x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- Resumen -->
        <div class="card">
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                    <div class="text-center">
                        @php
                            $score = $evaluation->percentage_score;
                            $scoreClass = match(true) {
                                $score >= 90 => 'score-excellent',
                                $score >= 80 => 'score-good',
                                $score >= 70 => 'score-average',
                                default => 'score-poor',
                            };
                        @endphp
                        <div class="text-4xl font-bold {{ $scoreClass }}">{{ number_format($score, 1) }}%</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Puntaje Final</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Campaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $evaluation->campaign->name }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Fecha</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $evaluation->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Estado</div>
                        @if($evaluation->status === 'visible_to_agent')
                            <span class="badge badge-warning">Pendiente Firma</span>
                        @elseif($evaluation->status === 'agent_responded')
                            <span class="badge badge-success">Firmada</span>
                        @elseif($evaluation->status === 'disputed')
                            <span class="badge badge-danger">En Disputa</span>
                        @else
                            <span class="badge badge-neutral">{{ ucfirst($evaluation->status) }}</span>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('transcripts.show', $evaluation->interaction) }}" class="btn-secondary btn-sm">
                            Ver Transcripción
                        </a>
                        
                        @if ($evaluation->type === 'ai')
                            @if ($manualEval = $evaluation->interaction->manualEvaluation)
                                <a href="{{ route('evaluations.show', $manualEval) }}" class="btn-ghost btn-sm mt-2 w-full justify-center text-indigo-600 dark:text-gray-400">
                                    Ver Evaluación Manual
                                </a>
                            @elseif(auth()->user()->hasAnyRole(['admin', 'qa_manager']))
                                <a href="{{ route('evaluations.create_manual', $evaluation->interaction) }}" class="btn-primary btn-sm mt-2 w-full justify-center">
                                    Evaluar Manualmente
                                </a>
                            @endif
                        @elseif ($evaluation->type === 'manual')
                             @if ($aiEval = $evaluation->interaction->aiEvaluation)
                                <a href="{{ route('evaluations.show', $aiEval) }}" class="btn-ghost btn-sm mt-2 w-full justify-center text-indigo-600 dark:text-gray-400">
                                    Ver Evaluación IA
                                </a>
                            @endif
                            <div class="mt-2 text-xs text-center text-gray-500">
                                Evaluado por: {{ $evaluation->evaluator->name ?? 'N/A' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Feedback Card -->
        @if($evaluation->ai_summary)
            <div class="card bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-900/20 dark:to-gray-800 border-indigo-100 dark:border-indigo-500/30">
                <div class="card-header border-b border-indigo-100 dark:border-indigo-500/30">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Feedback de Inteligencia Artificial</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                        {!! Str::markdown($evaluation->ai_summary) !!}
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
                                @endphp
                                
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $subAttribute->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Peso: {{ $subAttribute->weight_percent }}%</div>
                                        </div>
                                        <div class="text-right">
                                            @if($item)
                                                @if($item->status === 'compliant')
                                                    <span class="badge badge-success">✓ Cumple</span>
                                                @elseif($item->status === 'non_compliant')
                                                    <span class="badge badge-danger">✗ No Cumple</span>
                                                @elseif($item->status === 'not_found')
                                                    <span class="badge badge-warning">? No Encontrado</span>
                                                @else
                                                    <span class="badge badge-neutral">N/A</span>
                                                @endif
                                                
                                                @if($item->confidence)
                                                    <div class="text-xs text-gray-500 mt-1">Confianza: {{ number_format($item->confidence * 100, 0) }}%</div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if($item && $item->evidence_quote)
                                        <div class="mt-3 bg-white dark:bg-gray-900 border-l-4 border-indigo-500 dark:border-gray-600 p-3 rounded-r-lg">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Evidencia:</div>
                                            <div class="text-sm text-gray-700 dark:text-gray-300 italic">"{{ $item->evidence_quote }}"</div>
                                            @if($item->evidence_reference)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Referencia: {{ $item->evidence_reference }}</div>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if($item && $item->ai_notes)
                                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <strong>Notas:</strong> {{ $item->ai_notes }}
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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
        @elseif(auth()->user()->id === $evaluation->agent_id && $evaluation->status === 'visible_to_agent')
            <!-- Formulario de Respuesta -->
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Tu Respuesta</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('evaluations.respond', $evaluation) }}" x-data="{ responseType: 'accept' }" class="form-section">
                        @csrf
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Respuesta</label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="response_type" value="accept" x-model="responseType" class="form-checkbox">
                                    <span class="text-gray-700 dark:text-gray-300">Aceptar evaluación y comprometerme a mejorar</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="response_type" value="dispute" x-model="responseType" class="form-checkbox">
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
                            <label for="dispute_reason" class="form-label">Motivo de la Disputa <span class="text-rose-500">*</span></label>
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

        <!-- Resolución de Disputa -->
        @if($evaluation->dispute && !$evaluation->dispute->resolved_at && auth()->user()->hasAnyRole(['admin', 'qa_manager']))
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Resolver Disputa</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('disputes.resolve', $evaluation->dispute) }}" class="form-section">
                        @csrf
                        
                        <div class="form-group">
                            <label for="resolution_decision" class="form-label">Decisión <span class="text-rose-500">*</span></label>
                            <select name="resolution_decision" id="resolution_decision" class="form-select" required>
                                <option value="upheld">Mantener evaluación original</option>
                                <option value="overturned">Anular evaluación</option>
                                <option value="partial">Ajuste parcial</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="resolution_notes" class="form-label">Notas de Resolución <span class="text-rose-500">*</span></label>
                            <textarea name="resolution_notes" id="resolution_notes" rows="3" class="form-textarea" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="adjusted_score" class="form-label">Puntaje Ajustado (opcional)</label>
                            <input type="number" name="adjusted_score" id="adjusted_score" step="0.01" min="0" max="100" class="form-input">
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary btn-md">Resolver Disputa</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div>
            <a href="{{ route('evaluations.index') }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Listado
            </a>
        </div>
    </div>
</x-app-layout>
