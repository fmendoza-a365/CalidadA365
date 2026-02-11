<x-app-layout>
    <x-slot name="header">Evaluaciones</x-slot>

    <div class="card">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">Listado de Evaluaciones</h3>
                <span class="badge badge-neutral">{{ $evaluations->total() }}</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/30 border-b border-gray-100 dark:border-gray-800">
            <form method="GET" action="{{ route('evaluations.index') }}" class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 min-w-0">
                    <label for="campaign_id" class="form-label">Campaña</label>
                    <select name="campaign_id" id="campaign_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas las campañas</option>
                        @foreach($campaigns ?? [] as $campaign)
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
                        <option value="visible_to_agent" {{ request('status') == 'visible_to_agent' ? 'selected' : '' }}>Pendiente Firma</option>
                        <option value="agent_responded" {{ request('status') == 'agent_responded' ? 'selected' : '' }}>Firmada</option>
                        <option value="disputed" {{ request('status') == 'disputed' ? 'selected' : '' }}>En Disputa</option>
                    </select>
                </div>
                <a href="{{ route('evaluations.index') }}" class="btn-secondary btn-md whitespace-nowrap">Limpiar</a>
            </form>
        </div>

        {{-- Desktop Table View --}}
        <div class="hidden md:block table-container overflow-x-auto">
            <table class="table">
                <thead class="sticky top-0 z-10 text-xs uppercase tracking-wide bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="w-40 py-3 px-4 text-left font-medium">Fecha</th>
                        <th class="py-3 px-4 text-left font-medium">Campaña</th>
                        <th class="py-3 px-4 text-left font-medium">Asesor</th>
                        <th class="text-center w-32 py-3 px-4 font-medium">Puntaje</th>
                        <th class="text-center w-32 py-3 px-4 font-medium">Estado</th>
                        <th class="text-center w-32 py-3 px-4 font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($evaluations as $evaluation)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $evaluation->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $evaluation->campaign->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $evaluation->agent->name }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $score = $evaluation->percentage_score;
                                    $scoreClass = match(true) {
                                        $score >= 90 => 'text-emerald-600 dark:text-emerald-400',
                                        $score >= 80 => 'text-indigo-600 dark:text-indigo-400',
                                        $score >= 70 => 'text-amber-600 dark:text-amber-400',
                                        default => 'text-rose-600 dark:text-rose-400',
                                    };
                                @endphp
                                <span class="font-bold {{ $scoreClass }}">{{ number_format($score, 0) }}%</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($evaluation->status === 'visible_to_agent')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                        Pendiente Firma
                                    </span>
                                @elseif($evaluation->status === 'agent_responded')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        Firmada
                                    </span>
                                @elseif($evaluation->status === 'disputed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">
                                        En Disputa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                        {{ ucfirst(str_replace('_', ' ', $evaluation->status)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('evaluations.show', $evaluation) }}" class="btn-secondary btn-sm text-xs">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="empty-state">
                                    <div class="empty-state-icon mb-2">
                                        <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p>No hay evaluaciones disponibles</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Card View --}}
        <div class="md:hidden space-y-4">
            @forelse($evaluations as $evaluation)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">
                                {{ $evaluation->campaign->name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $evaluation->agent->name }}
                            </p>
                        </div>
                        <div class="ml-3">
                            @php
                                $score = $evaluation->percentage_score;
                                $scoreClass = match(true) {
                                    $score >= 90 => 'text-emerald-600 dark:text-emerald-400',
                                    $score >= 80 => 'text-indigo-600 dark:text-indigo-400',
                                    $score >= 70 => 'text-amber-600 dark:text-amber-400',
                                    default => 'text-rose-600 dark:text-rose-400',
                                };
                            @endphp
                            <div class="text-2xl font-bold {{ $scoreClass }}">
                                {{ number_format($score, 0) }}%
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $evaluation->created_at->format('d/m/Y H:i') }}
                        </span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        @if($evaluation->status === 'visible_to_agent')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                Pendiente Firma
                            </span>
                        @elseif($evaluation->status === 'agent_responded')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                Firmada
                            </span>
                        @elseif($evaluation->status === 'disputed')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">
                                En Disputa
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                {{ ucfirst(str_replace('_', ' ', $evaluation->status)) }}
                            </span>
                        @endif
                    </div>
                    
                    <a href="{{ route('evaluations.show', $evaluation) }}" class="btn-secondary btn-sm w-full text-center justify-center">
                        Ver Detalles
                    </a>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-12 text-center">
                    <div class="empty-state">
                        <div class="empty-state-icon mb-2">
                            <svg class="w-8 h-8 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">No hay evaluaciones disponibles</p>
                    </div>
                </div>
            @endforelse
        </div>
        
        @if($evaluations->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                {{ $evaluations->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
