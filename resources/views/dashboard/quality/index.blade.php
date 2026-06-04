<x-app-layout>
    <x-slot name="header">Dashboard Calidad</x-slot>

    <div x-data="{ tab: 'calidad' }" class="space-y-5">

        {{-- Filters --}}
        <form method="GET" action="{{ route('dashboard.quality') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Desde</span>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                       onchange="this.form.submit()">
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Hasta</span>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                       onchange="this.form.submit()">
            </div>
            <select name="campaign_id"
                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="this.form.submit()">
                <option value="">Todas las Campañas</option>
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" {{ request('campaign_id') == $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                @endforeach
            </select>
            @if(!auth()->user()->hasRole('agent'))
                <a href="{{ route('exports.calibration', request()->query()) }}" class="btn-secondary btn-md">Exportar calibración</a>
            @endif
        </form>

        {{-- KPI Row 1 --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-xl p-4 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white shadow-lg shadow-indigo-600/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">Evaluaciones</p>
                <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['total_evaluations']) }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-br from-sky-500 to-sky-600 text-white shadow-lg shadow-sky-500/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">Nota %</p>
                <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['average_score'], 2) }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-br from-cyan-500 to-teal-600 text-white shadow-lg shadow-teal-500/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">Nota sin MP%</p>
                <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['average_score_no_mp'], 2) }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-br from-rose-500 to-rose-600 text-white shadow-lg shadow-rose-500/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide"># Malas Prácticas</p>
                <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['mp_count']) }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">% Malas Prácticas</p>
                <p class="text-2xl font-extrabold mt-1">{{ $stats['mp_percentage'] }}%</p>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-500/20">
                <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">Feedback</p>
                <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['feedback_done']) }}</p>
                <p class="text-[10px] opacity-75 font-medium">{{ $stats['feedback_percentage'] }}% completado</p>
            </div>
        </div>

        {{-- KPI Row 2 --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @if(!auth()->user()->hasRole('agent'))
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/20">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Agentes Activos</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['active_agents'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ $stats['evals_per_agent'] }} evals/agente</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Monitores</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['active_monitors'] }}</p>
                    <p class="text-[10px] text-gray-400">{{ $stats['evals_per_monitor'] }} evals/monitor</p>
                </div>
            </div>
            @endif
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg bg-sky-50 dark:bg-sky-900/20">
                    <svg class="w-6 h-6 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Feedback Completo</p>
                    <p class="text-lg font-bold text-emerald-500">{{ $stats['feedback_percentage'] }}%</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg bg-rose-50 dark:bg-rose-900/20">
                    <svg class="w-6 h-6 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="text-[11px] text-gray-400 font-medium">Score Crítico (&lt;70)</p>
                    <p class="text-lg font-bold text-rose-500">{{ $stats['mp_percentage'] }}%</p>
                </div>
            </div>
        </div>

        {{-- Tab Buttons --}}
        <div class="flex flex-wrap gap-1.5 border-b border-gray-200 dark:border-gray-700 pb-3">
            <button @click="tab = 'calidad'" :class="tab === 'calidad' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Dashboard Calidad
            </button>
            <button @click="tab = 'mp'" :class="tab === 'mp' ? 'bg-rose-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Malas Prácticas
            </button>
            <button @click="tab = 'feedback'" :class="tab === 'feedback' ? 'bg-teal-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Seguimiento Feedback
            </button>
            @if(!auth()->user()->hasRole('agent'))
            <button @click="tab = 'calibracion'" :class="tab === 'calibracion' ? 'bg-sky-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h8M9 17H7a2 2 0 01-2-2V7m4 10a2 2 0 002 2h8a2 2 0 002-2v-6m-8-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg>
                Calibración IA
            </button>
            @endif
            <button @click="tab = 'ranking'" :class="tab === 'ranking' ? 'bg-amber-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Ranking Asesores
            </button>
            @if(!auth()->user()->hasRole('agent'))
            <button @click="tab = 'gestion'" :class="tab === 'gestion' ? 'bg-violet-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750'"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Dashboard Gestión
            </button>
            @endif
        </div>

        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 1: DASHBOARD CALIDAD                  --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'calidad'" x-transition.opacity class="space-y-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Módulo — Seguimiento Calidad
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="qd-card"><h4 class="qd-card-title">Calidad Emitida — Mes</h4><div id="chart-quality-month" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">Calidad Emitida — Semana</h4><div id="chart-quality-week" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">Calidad Emitida — Campaña</h4><div id="chart-quality-campaign" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">Calidad Emitida — Diario</h4><div id="chart-quality-daily" class="h-56"></div></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @if(!auth()->user()->hasRole('agent'))
                <div class="qd-card"><h4 class="qd-card-title">Calidad Emitida — Supervisor</h4><div id="chart-quality-supervisor" class="h-64"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">Motivos de Baja Calidad</h4><div id="chart-defects" class="h-64"></div></div>
                @else
                <div class="qd-card col-span-1 lg:col-span-2"><h4 class="qd-card-title">Motivos de Baja Calidad</h4><div id="chart-defects" class="h-64"></div></div>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 2: MALAS PRÁCTICAS                    --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'mp'" x-transition.opacity class="space-y-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Módulo — Detalle MP
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="qd-card"><h4 class="qd-card-title">MP — Mes</h4><div id="chart-mp-month" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">MP — Semana</h4><div id="chart-mp-week" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">MP — Campaña</h4><div id="chart-mp-campaign" class="h-56"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">MP — Diario</h4><div id="chart-mp-daily" class="h-56"></div></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @if(!auth()->user()->hasRole('agent'))
                <div class="qd-card"><h4 class="qd-card-title">MP — Supervisor</h4><div id="chart-mp-supervisor" class="h-64"></div></div>
                @endif
                <div class="qd-card @if(auth()->user()->hasRole('agent')) col-span-1 lg:col-span-2 @endif">
                    <h4 class="qd-card-title">Criterios Críticos Fallidos</h4>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Criterio</th>
                                    <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Fallos</th>
                                    <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Crítico</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topDefects as $defect)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2 px-2 text-gray-700 dark:text-gray-300">{{ $defect['label'] }}</td>
                                        <td class="py-2 px-2 text-center font-bold text-gray-900 dark:text-white">{{ $defect['count'] }}</td>
                                        <td class="py-2 px-2 text-center">
                                            @if($defect['is_critical'])
                                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">Sí</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center text-gray-400 text-xs">Sin datos</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 3: SEGUIMIENTO FEEDBACK               --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'feedback'" x-transition.opacity class="space-y-5">
            {{-- Feedback KPIs --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Evaluaciones</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ number_format($feedbackStats['total']) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Realizado</p>
                    <p class="text-2xl font-extrabold text-emerald-500 mt-1">{{ number_format($feedbackStats['done']) }}</p>
                    <p class="text-xs font-bold text-emerald-400">{{ $feedbackStats['done_pct'] }}%</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Pendiente</p>
                    <p class="text-2xl font-extrabold text-amber-500 mt-1">{{ number_format($feedbackStats['pending']) }}</p>
                    <p class="text-xs font-bold text-amber-400">{{ $feedbackStats['pending_pct'] }}%</p>
                </div>
                <div class="rounded-xl p-4 text-center bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20">
                    <p class="text-[11px] font-semibold opacity-80 uppercase tracking-wide">% Completado</p>
                    <p class="text-2xl font-extrabold mt-1">{{ $feedbackStats['done_pct'] }}%</p>
                </div>
            </div>

            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Seguimiento Feedback
            </h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @if(!auth()->user()->hasRole('agent'))
                <div class="qd-card"><h4 class="qd-card-title">Feedback — Supervisor(a)</h4><div id="chart-feedback-supervisor" class="h-64"></div></div>
                @endif
                <div class="qd-card @if(auth()->user()->hasRole('agent')) col-span-1 lg:col-span-2 @endif"><h4 class="qd-card-title">Feedback — Semanal</h4><div id="chart-feedback-week" class="h-64"></div></div>
            </div>

            @if(!auth()->user()->hasRole('agent'))
            {{-- Monitor Table --}}
            <div class="qd-card">
                <h4 class="qd-card-title">Resumen Monitor(a)</h4>
                <div class="overflow-x-auto mt-2">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Monitor</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide"># Evals</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Realizado</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Pendiente</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Realizado %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($feedbackSupervisor as $monitor)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-2 px-2 font-medium text-gray-800 dark:text-gray-200">{{ $monitor['label'] }}</td>
                                    <td class="py-2 px-2 text-center text-gray-600 dark:text-gray-400">{{ $monitor['total'] }}</td>
                                    <td class="py-2 px-2 text-center"><span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ $monitor['done'] }}</span></td>
                                    <td class="py-2 px-2 text-center"><span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold {{ $monitor['pending'] > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-400' }}">{{ $monitor['pending'] }}</span></td>
                                    <td class="py-2 px-2 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <div class="w-14 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5"><div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $monitor['done_pct'] }}%"></div></div>
                                            <span class="text-[10px] font-bold {{ $monitor['done_pct'] >= 80 ? 'text-emerald-500' : 'text-amber-500' }}">{{ $monitor['done_pct'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-gray-400 text-xs">Sin datos</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        @if(!auth()->user()->hasRole('agent'))
        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 4: CALIBRACION IA                     --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'calibracion'" x-transition.opacity class="space-y-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h8M9 17H7a2 2 0 01-2-2V7m4 10a2 2 0 002 2h8a2 2 0 002-2v-6m-8-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg>
                Calibración IA vs Monitor
            </h3>

            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Pares Comparados</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ number_format($calibrationSummary['pair_count']) }}</p>
                </div>
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Promedio IA</p>
                    <p class="text-2xl font-extrabold text-sky-500 mt-1">{{ number_format($calibrationSummary['average_ai_score'], 2) }}%</p>
                </div>
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Promedio Monitor</p>
                    <p class="text-2xl font-extrabold text-indigo-500 mt-1">{{ number_format($calibrationSummary['average_manual_score'], 2) }}%</p>
                </div>
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Diferencia Media</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $calibrationSummary['average_score_delta'] > 0 ? '+' : '' }}{{ number_format($calibrationSummary['average_score_delta'], 2) }} pp</p>
                    <p class="text-[10px] text-gray-400">{{ $calibrationSummary['direction_label'] }}</p>
                </div>
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Delta Absoluto</p>
                    <p class="text-2xl font-extrabold {{ $calibrationSummary['average_absolute_delta'] <= 5 ? 'text-emerald-500' : ($calibrationSummary['average_absolute_delta'] <= 10 ? 'text-amber-500' : 'text-rose-500') }} mt-1">{{ number_format($calibrationSummary['average_absolute_delta'], 2) }} pp</p>
                </div>
                <div class="qd-card">
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Acuerdo Criterios</p>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $calibrationSummary['item_agreement_rate'] === null ? 'N/A' : number_format($calibrationSummary['item_agreement_rate'], 1).'%' }}</p>
                    <p class="text-[10px] text-gray-400">{{ $calibrationSummary['high_delta_percentage'] }}% con delta alto</p>
                </div>
            </div>

            <div class="qd-card">
                <h4 class="qd-card-title">Últimos pares IA vs Monitor</h4>
                <div class="overflow-x-auto mt-2">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Campaña</th>
                                <th class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Asesor</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">IA</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Monitor</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Delta</th>
                                <th class="text-center py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">Acuerdo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($calibrationPairs as $pair)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-2 px-2 text-gray-700 dark:text-gray-300">{{ $pair['campaign'] }}</td>
                                    <td class="py-2 px-2 text-gray-700 dark:text-gray-300">{{ $pair['agent'] }}</td>
                                    <td class="py-2 px-2 text-center font-bold text-sky-500">{{ number_format($pair['ai_score'], 1) }}%</td>
                                    <td class="py-2 px-2 text-center font-bold text-indigo-500">{{ number_format($pair['manual_score'], 1) }}%</td>
                                    <td class="py-2 px-2 text-center font-bold {{ $pair['absolute_score_delta'] <= 5 ? 'text-emerald-500' : ($pair['absolute_score_delta'] <= 10 ? 'text-amber-500' : 'text-rose-500') }}">
                                        {{ $pair['score_delta'] > 0 ? '+' : '' }}{{ number_format($pair['score_delta'], 1) }} pp
                                    </td>
                                    <td class="py-2 px-2 text-center text-gray-600 dark:text-gray-400">
                                        {{ $pair['item_agreement_rate'] === null ? 'N/A' : number_format($pair['item_agreement_rate'], 1).'%' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-gray-400 text-xs">Aún no hay evaluaciones con par IA y manual en el período seleccionado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 4: RANKING ASESORES                   --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'ranking'" x-transition.opacity class="space-y-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Módulo — Ranking de Asesores
            </h3>

            <div class="qd-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">#</th>
                                <th class="text-left py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">Asesor</th>
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide"># Evals</th>
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">Nota</th>
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">Excelentes</th>
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">Críticos</th>
                                <th class="text-center py-2.5 px-2 font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agentRanking as $i => $agent)
                                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                        <td class="py-2.5 px-2 text-center">
                                                            @if($i === 0)
                                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 text-[10px] font-bold">1</span>
                                                            @elseif($i === 1)
                                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 text-[10px] font-bold">2</span>
                                                            @elseif($i === 2)
                                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-600 text-[10px] font-bold">3</span>
                                                            @else
                                                                <span class="text-gray-400 font-bold text-[10px]">{{ $i + 1 }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2.5 px-2">
                                                            <div class="flex items-center gap-2">
                                                                <div class="h-6 w-6 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-[9px]">{{ strtoupper(substr($agent['label'], 0, 2)) }}</div>
                                                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $agent['label'] }}</span>
                                                            </div>
                                                        </td>
                                                        <td class="py-2.5 px-2 text-center text-gray-600 dark:text-gray-400">{{ $agent['total_evals'] }}</td>
                                                        <td class="py-2.5 px-2 text-center">
                                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold
                                                                {{ $agent['avg_score'] >= 90 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                ($agent['avg_score'] >= 70 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                    'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400') }}">
                                                                {{ $agent['avg_score'] }}%
                                                            </span>
                                                        </td>
                                                        <td class="py-2.5 px-2 text-center font-bold text-emerald-500">{{ $agent['excellent'] }}</td>
                                                        <td class="py-2.5 px-2 text-center font-bold text-rose-500">{{ $agent['critical'] }}</td>
                                                        <td class="py-2.5 px-2 text-center">
                                                            @if($agent['avg_score'] >= 90)
                                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-500">
                                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                                    Excelente
                                                                </span>
                                                            @elseif($agent['avg_score'] >= 70)
                                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-500">
                                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                                    Regular
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-500">
                                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                                                    Crítico
                                                                </span>
                                                            @endif
                                                        </td>
                                                    </tr>
                            @empty
                                <tr><td colspan="7" class="py-6 text-center text-gray-400 text-xs">No hay datos suficientes</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(!auth()->user()->hasRole('agent'))
        {{-- ══════════════════════════════════════════ --}}
        {{-- TAB 5: DASHBOARD GESTIÓN                  --}}
        {{-- ══════════════════════════════════════════ --}}
        <div x-show="tab === 'gestion'" x-transition.opacity class="space-y-5">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Módulo — Gestión General
            </h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="qd-card">
                    <h4 class="qd-card-title">Evaluaciones — Campaña</h4>
                    <div id="chart-evals-campaign" class="h-64"></div>
                </div>
                <div class="qd-card">
                    <h4 class="qd-card-title">Ranking Asesor(a)</h4>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-1.5 px-2 font-semibold text-gray-400 uppercase">Asesor</th>
                                    <th class="text-center py-1.5 px-2 font-semibold text-gray-400 uppercase">Cant</th>
                                    <th class="text-center py-1.5 px-2 font-semibold text-gray-400 uppercase">% Part</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agentRanking as $agent)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-1.5 px-2 text-gray-700 dark:text-gray-300">{{ $agent['label'] }}</td>
                                        <td class="py-1.5 px-2 text-center font-bold text-gray-900 dark:text-white">{{ $agent['total_evals'] }}</td>
                                        <td class="py-1.5 px-2 text-center text-gray-500">{{ $stats['total_evaluations'] > 0 ? round(($agent['total_evals'] / $stats['total_evaluations']) * 100, 2) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="qd-card"><h4 class="qd-card-title">Calidad — Por Agente</h4><div id="chart-quality-agent" class="h-72"></div></div>
                <div class="qd-card"><h4 class="qd-card-title">Tendencia Diaria</h4><div id="chart-gestion-daily" class="h-72"></div></div>
            </div>
        </div>
        @endif

    </div>

    {{-- ═══════════════════ STYLES & CHARTS ═══════════════════ --}}
    @push('scripts')
        <style>
            .qd-card {
                @apply bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4;
            }
            .qd-card-title {
                @apply text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide;
            }
        </style>

        <script>
        (function() {
            let chartsBooted = false;

            function bootQualityCharts() {
                const charts = window.QA365Charts;

                if (!charts) {
                    if (typeof window.QA365LoadCharts === 'function') {
                        window.QA365LoadCharts().then(bootQualityCharts);
                        return;
                    }

                    window.addEventListener('qa365:chart-loader-ready', bootQualityCharts, { once: true });
                    window.addEventListener('qa365:charts-ready', bootQualityCharts, { once: true });
                    return;
                }

                if (chartsBooted) {
                    return;
                }

                chartsBooted = true;
                const colors = charts.colors();

                charts.combo('#chart-quality-month', @json($qualityMonth), {
                    barColor: colors.indigo,
                    lineColor: colors.cyan,
                });
                charts.combo('#chart-quality-week', @json($qualityWeek), {
                    barColor: colors.sky,
                    lineColor: colors.violet,
                });
                charts.bar('#chart-quality-campaign', @json($qualityCampaign), {
                    color: colors.indigo,
                    metric: 'avg_score',
                    valueName: 'Nota %',
                    max: 100,
                });
                charts.area('#chart-quality-daily', @json($qualityDaily), {
                    color: colors.indigo,
                });
                charts.combo('#chart-quality-supervisor', @json($qualitySupervisor), {
                    barColor: colors.teal,
                    lineColor: colors.amber,
                });
                charts.horizontalBar('#chart-defects', @json($topDefects), {
                    color: colors.rose,
                    valueName: 'Incidencias',
                });

                charts.bar('#chart-mp-month', @json($mpMonth), {
                    color: colors.rose,
                    valueName: 'Monitoreos',
                });
                charts.bar('#chart-mp-week', @json($mpWeek), {
                    color: colors.pink,
                    valueName: 'Monitoreos',
                });
                charts.bar('#chart-mp-campaign', @json($mpCampaign), {
                    color: colors.orange,
                    valueName: 'Monitoreos',
                });
                charts.bar('#chart-mp-daily', @json($mpDaily), {
                    color: colors.amber,
                    valueName: 'Monitoreos',
                });
                charts.bar('#chart-mp-supervisor', @json($mpSupervisor), {
                    color: colors.rose,
                    valueName: 'Monitoreos',
                });

                charts.stacked('#chart-feedback-supervisor', @json($feedbackSupervisor), {
                    doneColor: colors.teal,
                    pendingColor: colors.amber,
                });
                charts.stacked('#chart-feedback-week', @json($feedbackWeek), {
                    doneColor: colors.indigo,
                    pendingColor: colors.orange,
                });

                charts.horizontalBar('#chart-evals-campaign', @json($evalsByCampaign), {
                    color: colors.violet,
                    valueName: 'Evaluaciones',
                });
                charts.horizontalBar('#chart-quality-agent', @json($agentRanking), {
                    color: colors.indigo,
                    metric: 'avg_score',
                    valueName: 'Nota %',
                    max: 100,
                });
                charts.area('#chart-gestion-daily', @json($qualityDaily), {
                    color: colors.violet,
                });

                charts.resizeAll();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootQualityCharts);
            } else {
                bootQualityCharts();
            }
        })();
        </script>
    @endpush
</x-app-layout>
