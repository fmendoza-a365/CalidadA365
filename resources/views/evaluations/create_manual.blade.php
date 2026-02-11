<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Evaluación Manual
                </h2>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $interaction->agent->name }} • {{ $interaction->campaign->name }}
                </div>
            </div>
            
            <div class="flex gap-3">
                 <a href="{{ route('transcripts.show', $interaction) }}" target="_blank" class="btn-secondary btn-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Ver Transcripción
                </a>
                <a href="{{ route('evaluations.show', $interaction->aiEvaluation) }}" target="_blank" class="btn-ghost btn-sm text-indigo-600 dark:text-gray-400 flex items-center gap-2">
                     <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Ref. Evaluación IA
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto space-y-6 md:space-y-8 pb-12 px-4 md:px-0">
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

        <form method="POST" action="{{ route('evaluations.store_manual', $interaction) }}" class="space-y-8">
            @csrf
            <input type="hidden" name="form_version_id" value="{{ $formVersion->id }}">

            @foreach($formVersion->formAttributes as $attribute)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
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
                            @endphp

                            <div class="p-6 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                    
                                    <!-- Columna Izquierda: Descripción e IA -->
                                    <div class="lg:col-span-7 space-y-3">
                                        <div>
                                            <div class="text-base font-semibold text-gray-900 dark:text-white mb-1">
                                                {{ $subAttribute->name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                                {{ $subAttribute->description }}
                                            </div>
                                        </div>

                                        @if($aiItem)
                                            <div class="relative mt-4 pl-4 border-l-4 border-indigo-400 dark:border-gray-500 bg-indigo-50/50 dark:bg-gray-800 p-3 rounded-r-lg">
                                                <div class="absolute -left-1.5 -top-2 bg-indigo-500 dark:bg-gray-600 text-white text-[10px] uppercase font-bold px-1.5 py-0.5 rounded">
                                                    IA: {{ $aiStatus === 'compliant' ? 'SI' : ($aiStatus === 'non_compliant' ? 'NO' : 'N/A') }}
                                                </div>
                                                
                                                @if($aiItem->evidence_quote)
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-2">
                                                        "{{ Str::limit($aiItem->evidence_quote, 150) }}"
                                                    </p>
                                                @endif
                                                @if($aiItem->ai_notes)
                                                    <p class="text-xs text-indigo-600 dark:text-gray-400 font-medium">
                                                        Note: {{ $aiItem->ai_notes }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Columna Derecha: Controles Manuales -->
                                    <div class="lg:col-span-5 flex flex-col justify-start space-y-4">
                                        <input type="hidden" name="items[{{ $subAttribute->id }}][subattribute_id]" value="{{ $subAttribute->id }}">
                                        
                                        <!-- Botones de Acción -->
                                        <div class="grid grid-cols-3 gap-2">
                                            <label class="cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="compliant" class="peer sr-only" required {{ $aiStatus === 'compliant' ? 'checked' : '' }}>
                                                <div class="h-10 flex items-center justify-center rounded-lg border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium text-sm transition-all
                                                    peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500 peer-checked:shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-500">
                                                    Cumple
                                                </div>
                                            </label>
                                            
                                            <label class="cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="non_compliant" class="peer sr-only" {{ $aiStatus === 'non_compliant' ? 'checked' : '' }}>
                                                <div class="h-10 flex items-center justify-center rounded-lg border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium text-sm transition-all
                                                    peer-checked:bg-rose-500 peer-checked:text-white peer-checked:border-rose-500 peer-checked:shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-500">
                                                    No
                                                </div>
                                            </label>
                                            
                                            <label class="cursor-pointer">
                                                <input type="radio" name="items[{{ $subAttribute->id }}][status]" value="not_found" class="peer sr-only" {{ $aiStatus === 'not_found' ? 'checked' : '' }}>
                                                <div class="h-10 flex items-center justify-center rounded-lg border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium text-sm transition-all
                                                    peer-checked:bg-gray-500 peer-checked:text-white peer-checked:border-gray-500 peer-checked:shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-500">
                                                    N/A
                                                </div>
                                            </label>
                                        </div>

                                        <!-- Campo de Notas -->
                                        <div>
                                            <textarea name="items[{{ $subAttribute->id }}][notes]" rows="2" 
                                                class="w-full rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm focus:border-indigo-500 dark:focus:border-gray-400 focus:ring-indigo-500 dark:focus:ring-gray-400" 
                                                placeholder="Observaciones manuales...">{{ $aiItem ? $aiItem->ai_notes : '' }}</textarea>
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
                <div class="max-w-5xl mx-auto flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                    <div class="text-sm text-gray-500 text-center sm:text-left">
                        <span class="hidden md:inline">Revisa cuidadosamente cada punto antes de guardar.</span>
                        <span class="md:hidden">Revisa cada punt antes de guardar</span>
                    </div>
                    <div class="flex gap-2 sm:gap-4">
                        <a href="{{ route('evaluations.index') }}" class="btn-ghost text-gray-600 flex-1 sm:flex-none justify-center">Cancelar</a>
                        <button type="submit" class="btn-primary flex-1 sm:flex-none justify-center px-6 sm:px-8 shadow-lg transform hover:-translate-y-0.5 transition-all">
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
