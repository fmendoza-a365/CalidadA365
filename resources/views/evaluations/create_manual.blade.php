<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Evaluación Manual Final
                </h2>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $interaction->agent->name }} • {{ $interaction->campaign?->displayName() ?? 'Sin campaña' }}
                </div>
            </div>
            
            <div class="flex gap-3">
                 <a href="{{ route('transcripts.show', $interaction) }}" target="_blank" class="btn-secondary btn-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Ver Transcripción
                </a>
                @if($interaction->aiEvaluation)
                <a href="{{ route('evaluations.show', $interaction->aiEvaluation) }}" target="_blank" class="btn-ghost btn-sm text-indigo-600 dark:text-gray-400 flex items-center gap-2">
                     <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Ref. Evaluación IA
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6 pb-24 px-4 md:px-6">
        <!-- Info Card Compact -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Fecha</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $interaction->occurred_at->format('d/m/Y H:i') }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 uppercase tracking-wider font-semibold">ID Interacción</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $interaction->id }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Formulario</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $formVersion->form->name }} (v{{ $formVersion->version_number }})</span>
                </div>
                <div class="flex items-center justify-start sm:justify-end">
                     <span class="status-badge status-{{ $interaction->status }}">
                        {{ ucfirst($interaction->status) }}
                    </span>
                </div>
            </div>
        </div>

        @if($aiEvaluation?->isReviewClaimedBy(auth()->user()))
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200">
                <span class="font-semibold">Caso reservado para ti.</span>
                @if($aiEvaluation->review_claim_expires_at)
                    La reserva vence en {{ $aiEvaluation->review_claim_expires_at->diffForHumans(null, true) }} si no guardas la evaluación.
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('evaluations.store_manual', $interaction) }}" class="space-y-8">
            @csrf
            <input type="hidden" name="form_version_id" value="{{ $formVersion->id }}">

            @foreach($formVersion->formAttributes as $attribute)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                            {{ $attribute->name }}
                        </h3>
                        <span class="bg-indigo-100 text-indigo-700 dark:bg-gray-700 dark:text-gray-200 py-1 px-3 rounded-full text-xs font-bold">
                            {{ $attribute->weight }}%
                        </span>
                    </div>
                    
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($attribute->subAttributes as $subAttribute)
                            @php
                                $aiItem = $aiEvaluation ? $aiEvaluation->items->firstWhere('subattribute_id', $subAttribute->id) : null;
                                $aiStatus = $aiItem ? $aiItem->status : null;
                                $manualStatus = old("items.{$subAttribute->id}.status", $aiStatus);
                                $aiStatusLabel = match ($aiStatus) {
                                    'compliant' => 'Cumple',
                                    'non_compliant' => 'No',
                                    'not_found' => 'N/A',
                                    default => 'Sin lectura',
                                };
                                $aiStatusClass = match ($aiStatus) {
                                    'compliant' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    'non_compliant' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                    'not_found' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                };
                            @endphp

                            <div class="p-5 hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_28rem] gap-5">
                                    <div class="min-w-0 space-y-3">
                                        <div>
                                            <div class="text-base font-semibold text-gray-900 dark:text-white mb-1">
                                                {{ $subAttribute->name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                                {{ $subAttribute->concept ?? $subAttribute->guidelines ?? 'Sin descripcion registrada.' }}
                                            </div>
                                        </div>

                                        @if($aiItem)
                                            <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-900/50">
                                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                        Referencia IA
                                                    </span>
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $aiStatusClass }}">
                                                        {{ $aiStatusLabel }}
                                                    </span>
                                                </div>
                                                
                                                @if($aiItem->evidence_quote)
                                                    <p class="mb-2 text-sm italic leading-relaxed text-gray-700 dark:text-gray-300">
                                                        "{{ Str::limit($aiItem->evidence_quote, 150) }}"
                                                    </p>
                                                @endif
                                                @if($aiItem->ai_notes)
                                                    <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                                        {{ $aiItem->ai_notes }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 space-y-3">
                                        <input type="hidden" name="items[{{ $subAttribute->id }}][subattribute_id]" value="{{ $subAttribute->id }}">
                                        
                                        <div class="grid grid-cols-3 gap-2">
                                            <label class="relative block h-10 cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="compliant" class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" required {{ $manualStatus === 'compliant' ? 'checked' : '' }}>
                                                <span class="pointer-events-none flex h-full items-center justify-center rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 transition-colors dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300
                                                    peer-checked:border-emerald-500 peer-checked:bg-emerald-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500/40">
                                                    Cumple
                                                </span>
                                            </label>
                                            
                                            <label class="relative block h-10 cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="non_compliant" class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" {{ $manualStatus === 'non_compliant' ? 'checked' : '' }}>
                                                <span class="pointer-events-none flex h-full items-center justify-center rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 transition-colors dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300
                                                    peer-checked:border-rose-500 peer-checked:bg-rose-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-rose-500/40">
                                                    No
                                                </span>
                                            </label>
                                            
                                            <label class="relative block h-10 cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="not_found" class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" {{ $manualStatus === 'not_found' ? 'checked' : '' }}>
                                                <span class="pointer-events-none flex h-full items-center justify-center rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 transition-colors dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300
                                                    peer-checked:border-gray-500 peer-checked:bg-gray-500 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500/40">
                                                    N/A
                                                </span>
                                            </label>
                                        </div>

                                        <div>
                                            <textarea name="items[{{ $subAttribute->id }}][notes]" rows="3"
                                                data-testid="manual-notes-{{ $subAttribute->id }}"
                                                class="form-textarea min-h-[76px] max-h-[120px] text-sm"
                                                placeholder="Observación del monitor si corriges o confirmas este criterio.">{{ old("items.{$subAttribute->id}.notes") }}</textarea>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <!-- Action Bar Sticky Bottom -->
            <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-3 md:p-4 shadow-lg z-50">
                <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 px-4 md:px-6">
                    <div class="text-sm text-gray-500 text-center sm:text-left">
                        <span class="hidden md:inline">Revisa cuidadosamente cada punto antes de guardar.</span>
                        <span class="md:hidden">Revisa cada punto antes de guardar</span>
                    </div>
                    <div class="flex gap-2 sm:gap-4">
                        <a href="{{ route('evaluations.index') }}" class="btn-ghost text-gray-600 flex-1 sm:flex-none justify-center">Cancelar</a>
                        <button type="submit" class="btn-primary flex-1 sm:flex-none justify-center px-6 sm:px-8 shadow-lg transform hover:-translate-y-0.5 transition-all">
                            Guardar y Publicar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
