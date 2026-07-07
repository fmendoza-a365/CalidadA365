<x-app-layout>
    <x-slot name="header">Perfil de Agente: Calidad</x-slot>

    <div class="space-y-6 w-full mx-auto">

        {{-- Controls & Date Filter (Hextech Style) --}}
        <div
            class="bg-indigo-50 dark:bg-gray-900 border border-indigo-100 dark:border-gray-700/50 rounded-xl p-4 shadow-sm dark:shadow-2xl flex flex-wrap items-center justify-between gap-4 relative overflow-hidden">
            <div
                class="absolute inset-0 bg-gradient-to-r from-blue-100/50 to-purple-100/30 dark:from-blue-900/20 dark:to-purple-900/10 pointer-events-none">
            </div>
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500">
            </div>

            <div class="flex items-center gap-3 relative z-10">
                <div
                    class="h-10 w-10 rounded-lg bg-white dark:bg-gray-800 border border-indigo-200 dark:border-gray-600 flex items-center justify-center text-indigo-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-gray-900 dark:text-gray-100 font-bold tracking-wider uppercase text-sm">Filtro de
                        Temporada</h3>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">Selecciona el periodo de
                        análisis</p>
                </div>
            </div>

            <form method="GET" action="{{ route('dashboard') }}"
                class="flex flex-wrap items-center gap-3 relative z-10">
                <div class="flex items-center gap-2">
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                        class="text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:border-indigo-500 dark:focus:border-blue-500 focus:ring-indigo-500 dark:focus:ring-blue-500"
                        onchange="this.form.submit()">
                </div>
                <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                <div class="flex items-center gap-2">
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                        class="text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:border-indigo-500 dark:focus:border-blue-500 focus:ring-indigo-500 dark:focus:ring-blue-500"
                        onchange="this.form.submit()">
                </div>
                @php
                    $parentCampaigns = $campaigns->whereNull('parent_id');
                    $subcampaignsByParent = $campaigns->whereNotNull('parent_id')->groupBy('parent_id');
                @endphp
                <div x-data="{
                    parentCampaignId: '{{ request('parent_campaign_id', $filters['parent_campaign_id'] ?? '') }}',
                    campaignId: '{{ request('campaign_id', $filters['campaign_id'] ?? '') }}',
                    subcampaigns: {{ json_encode($subcampaignsByParent->map(fn($group) => $group->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values())) }},
                    get availableSubcampaigns() {
                        return this.parentCampaignId ? (this.subcampaigns[this.parentCampaignId] || []) : [];
                    }
                }" class="flex flex-wrap items-center gap-3">
                    <select name="parent_campaign_id" x-model="parentCampaignId" 
                        @change="campaignId = ''; $nextTick(() => $el.form.submit())"
                        class="text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:border-indigo-500 dark:focus:border-blue-500 focus:ring-indigo-500 dark:focus:ring-blue-500 pl-3 pr-8 py-2">
                        <option value="">Todas las campañas</option>
                        @foreach($parentCampaigns as $pCamp)
                            <option value="{{ $pCamp->id }}">{{ $pCamp->name }}</option>
                        @endforeach
                    </select>

                    <select name="campaign_id" x-model="campaignId"
                        @change="$el.form.submit()"
                        :disabled="!parentCampaignId || availableSubcampaigns.length === 0"
                        class="text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:border-indigo-500 dark:focus:border-blue-500 focus:ring-indigo-500 dark:focus:ring-blue-500 pl-3 pr-8 py-2 disabled:opacity-50">
                        <option value="">Todas las subcampañas</option>
                        <template x-for="sub in availableSubcampaigns" :key="sub.id">
                            <option :value="sub.id" x-text="sub.name" :selected="campaignId == sub.id"></option>
                        </template>
                    </select>
                </div>
            </form>
        </div>

        {{-- Banners and Stats --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Profile Banner (Left) --}}
            <div
                class="col-span-1 lg:col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700/50 rounded-xl overflow-hidden shadow-sm dark:shadow-2xl relative group">
                <div
                    class="h-24 bg-gradient-to-br from-indigo-100 via-blue-50 to-white dark:from-indigo-900 dark:via-gray-900 dark:to-[#091428] relative border-b border-gray-100 dark:border-transparent">
                    <div
                        class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/hexellence.png')] opacity-5 dark:opacity-10">
                    </div>
                </div>

                <div class="px-6 pb-6 relative">
                    <div class="flex justify-center -mt-12 mb-4 relative">
                        <div class="relative">
                            <img src="{{ auth()->user()->avatar_url }}"
                                class="w-24 h-24 rounded-full border-4 border-[#C8AA6E] object-cover shadow-[0_0_15px_rgba(200,170,110,0.5)] z-10 relative bg-white dark:bg-transparent">
                            <div
                                class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 bg-white dark:bg-[#1E2328] border border-[#C8AA6E] text-gray-800 dark:text-[#F0E6D2] text-[10px] font-bold px-2.5 py-0.5 rounded-full z-20 whitespace-nowrap shadow-sm">
                                Evals: {{ number_format($stats['total_evaluations']) }}
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</h2>
                        <p
                            class="text-xs text-indigo-600 dark:text-[#0AC8B9] font-medium tracking-widest uppercase mt-0.5">
                            Asesor de Calidad</p>
                    </div>

                    <div
                        class="bg-gray-50 dark:bg-[#010A13] border border-gray-100 dark:border-[#3C3C41] rounded-lg p-4 relative overflow-hidden">
                        <div
                            class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-[#C8AA6E]/10 dark:from-[#C8AA6E]/20 to-transparent rounded-bl-full pointer-events-none">
                        </div>

                        {{-- League Icon & Name --}}
                        <div class="flex items-center gap-3 mb-4">
                            <div class="filter drop-shadow-[0_0_8px_rgba(0,0,0,0.1)] dark:drop-shadow-[0_0_8px_rgba(255,255,255,0.3)]">
                                {!! $league['icon'] !!}
                            </div>
                            <div>
                                <h3 class="{{ $league['color'] }} font-black text-lg uppercase tracking-wider">
                                    {{ $league['name'] }}
                                </h3>
                                @php
                                    $nextThreshold = $stats['average_score'] >= 90 ? 100 : ($stats['average_score'] >= 80 ? 90 : ($stats['average_score'] >= 70 ? 80 : 70));
                                    $prevThreshold = $stats['average_score'] >= 90 ? 90 : ($stats['average_score'] >= 80 ? 80 : ($stats['average_score'] >= 70 ? 70 : 0));
                                    $progress = $nextThreshold > $prevThreshold ? min(100, round((($stats['average_score'] - $prevThreshold) / ($nextThreshold - $prevThreshold)) * 100)) : 100;
                                @endphp
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                    @if($stats['average_score'] >= 90)
                                        Nivel máximo alcanzado
                                    @else
                                        {{ number_format($nextThreshold - $stats['average_score'], 1) }}% para el siguiente nivel
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Progress Bar to Next League --}}
                        @if($stats['average_score'] < 90)
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-[9px] text-gray-500 mb-1">
                                    <span>{{ $league['name'] }}</span>
                                    <span>{{ $stats['average_score'] >= 80 ? 'Q1 - Diamante' : ($stats['average_score'] >= 70 ? 'Q2 - Oro' : 'Q3 - Plata') }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $stats['average_score'] >= 80 ? 'bg-cyan-500' : ($stats['average_score'] >= 70 ? 'bg-amber-500' : 'bg-slate-400') }}"
                                        style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-white dark:bg-gray-800/50 rounded-lg p-2.5 text-center border border-gray-100 dark:border-gray-700/50">
                                <p class="text-[9px] text-gray-500 uppercase tracking-wide mb-0.5">Efectividad</p>
                                <p class="text-base font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($stats['average_score'], 1) }}%
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800/50 rounded-lg p-2.5 text-center border border-gray-100 dark:border-gray-700/50">
                                <p class="text-[9px] text-gray-500 uppercase tracking-wide mb-0.5">Sin Críticas</p>
                                <p class="text-base font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($stats['average_score_no_mp'], 1) }}%
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800/50 rounded-lg p-2.5 text-center border border-gray-100 dark:border-gray-700/50">
                                <p class="text-[9px] text-gray-500 uppercase tracking-wide mb-0.5">Evaluaciones</p>
                                <p class="text-base font-bold text-gray-900 dark:text-gray-100">
                                    {{ $stats['total_evaluations'] }}
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800/50 rounded-lg p-2.5 text-center border border-gray-100 dark:border-gray-700/50">
                                <p class="text-[9px] text-gray-500 uppercase tracking-wide mb-0.5">Feedback</p>
                                <p class="text-base font-bold {{ $stats['feedback_percentage'] >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ number_format($stats['feedback_percentage'], 0) }}%
                                </p>
                            </div>
                        </div>

                        {{-- Critical Failures --}}
                        @if($stats['mp_count'] > 0)
                            <div class="mt-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 rounded-lg p-2.5 flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ $stats['mp_count'] }} falla(s) crítica(s)</p>
                                    <p class="text-[10px] text-rose-600 dark:text-rose-400">{{ $stats['mp_percentage'] }}% de las evaluaciones</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Match History (Right) --}}
            <div class="col-span-1 lg:col-span-2 space-y-4">
                <div
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700/50 rounded-xl p-4 shadow-sm dark:shadow-2xl h-full flex flex-col">
                    <div
                        class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-gray-800">
                        <h3
                            class="text-gray-800 dark:text-[#F0E6D2] font-bold tracking-wider uppercase text-sm flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#C8AA6E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Historial de Evaluaciones
                        </h3>
                        <span class="text-[10px] text-gray-500 uppercase font-medium">
                            @if($matchHistory instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                                Total: {{ $matchHistory->total() }}
                            @else
                                Últimas {{ $matchHistory->count() }} evaluaciones
                            @endif
                        </span>
                    </div>

                    <div class="space-y-2.5 flex-1 overflow-y-auto pr-1 custom-scrollbar">
                        @forelse($matchHistory as $match)
                            @php
                                $isVictory = $match->percentage_score >= 90;
                                $isDefeat = $match->percentage_score < 70;

                                $bgClass = $isVictory ? 'bg-indigo-50/50 hover:bg-indigo-100/50 dark:bg-blue-900/10 dark:hover:bg-blue-900/20' : ($isDefeat ? 'bg-rose-50/50 hover:bg-rose-100/50 dark:bg-rose-900/10 dark:hover:bg-rose-900/20' : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800/30 dark:hover:bg-gray-800/50');
                                $borderClass = $isVictory ? 'border-l-indigo-500 dark:border-l-blue-500' : ($isDefeat ? 'border-l-rose-500' : 'border-l-gray-400 dark:border-l-gray-500');
                                $textClass = $isVictory ? 'text-indigo-600 dark:text-blue-400' : ($isDefeat ? 'text-rose-600 dark:text-rose-400' : 'text-gray-600 dark:text-gray-300');
                                $statusText = $isVictory ? 'EXCELENTE' : ($isDefeat ? 'MEJORABLE' : 'REGULAR');
                            @endphp

                            <a href="{{ route('evaluations.show', $match->id) }}"
                                class="block border-l-4 {{ $borderClass }} {{ $bgClass }} rounded-r-lg border-y border-r border-gray-200 dark:border-[#2B2B2F] p-3 transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-20 shrink-0 text-center">
                                            <p class="{{ $textClass }} font-bold text-xs tracking-wider mb-0.5">
                                                {{ $statusText }}</p>
                                            <div
                                                class="w-full bg-gray-200 dark:bg-[#010A13] rounded-full h-1.5 mt-1 overflow-hidden">
                                                <div class="{{ $isVictory ? 'bg-indigo-500 dark:bg-blue-500' : ($isDefeat ? 'bg-rose-500' : 'bg-gray-400') }} h-full"
                                                    style="width: {{ $match->percentage_score }}%"></div>
                                            </div>
                                        </div>

                                        <div>
                                            <h4
                                                class="text-sm font-bold text-gray-900 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-blue-300 transition-colors">
                                                {{ number_format($match->percentage_score, 1) }}% <span
                                                    class="text-gray-500 text-xs font-normal">|
                                                    {{ $match->campaign?->displayName() ?? 'Sin campaña' }}</span></h4>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Auditor:
                                                {{ $match->evaluator->full_name ?? 'Sistema Automático' }}</p>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $match->created_at->diffForHumans() }}</p>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-600">
                                            {{ $match->created_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div
                                class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500 py-8">
                                <svg class="w-12 h-12 mb-3 opacity-20" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <p class="text-xs uppercase tracking-widest">Sin evaluaciones recientes en este periodo</p>
                            </div>
                        @endforelse
                    </div>

                    @if($matchHistory instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $matchHistory->hasPages())
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                            {{ $matchHistory->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ladder & Defects --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Clasificatoria (Solo/Duo) --}}
            <div
                class="col-span-1 lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700/50 rounded-xl p-4 shadow-sm dark:shadow-2xl">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-gray-800">
                    <h3
                        class="text-gray-800 dark:text-[#F0E6D2] font-bold tracking-wider uppercase text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#C8AA6E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ranking de Equipo
                    </h3>
                    <span class="text-[10px] text-gray-500 uppercase font-medium">
                        @if($agentRanking instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                            {{ $agentRanking->total() }} agentes · 10 por página
                        @else
                            Top Agentes
                        @endif
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr
                                class="border-b border-gray-200 dark:border-gray-800 text-gray-500 uppercase tracking-wider text-[9px]">
                                <th class="text-center py-2 px-2 font-semibold">Pos</th>
                                <th class="text-left py-2 px-2 font-semibold">Agente</th>
                                <th class="text-center py-2 px-2 font-semibold">Rendimiento</th>
                                <th class="text-center py-2 px-2 font-semibold">Efectividad</th>
                                <th class="text-center py-2 px-2 font-semibold">Evals</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $rankingBase = $agentRanking instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                                    ? max(0, ($agentRanking->firstItem() ?? 1) - 1)
                                    : 0;
                            @endphp
                            @forelse($agentRanking as $i => $agent)
                                @php
                                    $rank = $rankingBase + $i + 1;
                                    $score = $agent['avg_score'];
                                    if ($score >= 90) {
                                        $lName = 'Q1 - Diamante';
                                    } elseif ($score >= 80) {
                                        $lName = 'Q2 - Oro';
                                    } elseif ($score >= 70) {
                                        $lName = 'Q3 - Plata';
                                    } else {
                                        $lName = 'Q4 - Bronce';
                                    }

                                    $isMe = $agent['id'] === auth()->id();
                                @endphp
                                <tr
                                    class="border-b border-gray-100 dark:border-[#2B2B2F] hover:bg-gray-50 dark:hover:bg-[#1E2328] transition-colors {{ $isMe ? 'bg-indigo-50/50 dark:bg-[#091428] border-l-2 border-l-indigo-500 dark:border-l-[#C8AA6E]' : '' }}">
                                    <td class="py-2.5 px-2 text-center">
                                        @if($rank === 1)
                                            <span
                                                class="inline-flex items-center justify-center w-5 h-5 rounded text-[#F0E6D2] bg-gradient-to-br from-yellow-400 to-yellow-600 dark:from-yellow-500 dark:to-yellow-700 font-bold text-[10px] border border-yellow-300 dark:border-yellow-600 shadow-sm">1</span>
                                        @elseif($rank === 2)
                                            <span
                                                class="inline-flex items-center justify-center w-5 h-5 rounded text-gray-700 dark:text-gray-100 bg-gradient-to-br from-gray-200 to-gray-400 dark:from-gray-300 dark:to-gray-500 font-bold text-[10px] border border-gray-300 dark:border-gray-400 shadow-sm">2</span>
                                        @elseif($rank === 3)
                                            <span
                                                class="inline-flex items-center justify-center w-5 h-5 rounded text-orange-900 bg-gradient-to-br from-orange-200 to-orange-400 dark:from-orange-300 dark:to-orange-500 font-bold text-[10px] border border-orange-300 dark:border-orange-400 shadow-sm">3</span>
                                        @else
                                            <span class="text-gray-500 font-bold text-[10px]">{{ $rank }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-2">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-6 w-6 rounded border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-[#010A13] flex items-center justify-center text-gray-500 dark:text-gray-400 font-bold text-[8px]">
                                                {{ strtoupper(substr($agent['label'], 0, 2)) }}</div>
                                            <span
                                                class="font-bold {{ $isMe ? 'text-indigo-600 dark:text-[#C8AA6E]' : 'text-gray-700 dark:text-gray-300' }}">{{ $agent['label'] }}
                                                {{ $isMe ? '(Tú)' : '' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-2 text-center">
                                        <span
                                            class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ $lName }}</span>
                                    </td>
                                    <td class="py-2.5 px-2 text-center">
                                        <span
                                            class="font-bold {{ $score >= 90 ? 'text-indigo-600 dark:text-blue-400' : ($score >= 70 ? 'text-gray-600 dark:text-gray-300' : 'text-rose-600 dark:text-rose-400') }}">{{ number_format($score, 1) }}%</span>
                                    </td>
                                    <td class="py-2.5 px-2 text-center text-gray-600 dark:text-gray-500 font-medium">
                                        {{ $agent['total_evals'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-500 text-xs">Aún no hay agentes
                                        clasificados en tu equipo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($agentRanking instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $agentRanking->hasPages())
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                        {{ $agentRanking->links() }}
                    </div>
                @endif
            </div>

            {{-- Weaknesses (Defects) --}}
            <div
                class="col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700/50 rounded-xl p-4 shadow-sm dark:shadow-2xl">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-gray-800">
                    <h3
                        class="text-gray-800 dark:text-[#F0E6D2] font-bold tracking-wider uppercase text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Oportunidades de Mejora
                    </h3>
                </div>

                <div class="space-y-3">
                    @forelse(array_slice($topDefects, 0, 5) as $defect)
                        <div
                            class="bg-gray-50 dark:bg-[#010A13] border border-gray-100 dark:border-[#2B2B2F] rounded p-2.5 flex items-center justify-between">
                            <div class="flex-1 min-w-0 pr-2">
                                <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-300 truncate"
                                    title="{{ $defect['label'] }}">{{ $defect['label'] }}</p>
                                @if($defect['is_critical'])
                                    <span
                                        class="text-[8px] uppercase tracking-wider text-rose-600 dark:text-rose-500 font-bold bg-rose-100 dark:bg-rose-900/30 px-1 rounded block w-max mt-0.5">Crítico</span>
                                @endif
                            </div>
                            <div class="text-right pl-2 border-l border-gray-200 dark:border-gray-800">
                                <p class="text-sm font-black text-rose-600 dark:text-rose-400">{{ $defect['count'] }}</p>
                                <p class="text-[8px] text-gray-500 uppercase">Fallos</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p class="text-xs">¡Excelente desempeño! Sin puntos débiles detectados.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <style>
            .custom-scrollbar::-webkit-scrollbar {
                width: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #3C3C41;
                border-radius: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #C8AA6E;
            }
        </style>
    @endpush
</x-app-layout>
