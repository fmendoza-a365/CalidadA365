<x-app-layout>
    @php
        $findings = $findings ?? ($insight->key_findings ?? []);
        $snapshot = $snapshot ?? data_get($findings, 'report_snapshot', []);
        $scope = data_get($snapshot, 'scope', []);
        $metrics = data_get($snapshot, 'metrics', []);
        $scoreDistribution = collect(data_get($snapshot, 'score_distribution', []));
        $topFailedCriteria = collect(data_get($snapshot, 'top_failed_criteria', []));
        $agentPerformance = collect(data_get($snapshot, 'agent_performance', []));
        $campaignBreakdown = collect(data_get($snapshot, 'campaign_breakdown', []));
        $slides = collect(data_get($findings, 'presentation_slides', []));
        $maxBandCount = max(1, (int) $scoreDistribution->max('count'));
        $renderMarkdown = fn ($value) => \Illuminate\Support\Str::markdown((string) $value, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $typeLabel = [
            'operational' => 'Operaciones',
            'strategic' => 'Cliente',
            'combined' => 'Combinado',
        ][$insight->type] ?? ucfirst($insight->type);
        $scoreTone = function ($score) {
            $score = (float) $score;

            return $score >= 90
                ? 'text-emerald-600 dark:text-emerald-300'
                : ($score >= 80
                    ? 'text-sky-600 dark:text-sky-300'
                    : ($score >= 70
                        ? 'text-amber-600 dark:text-amber-300'
                        : 'text-rose-600 dark:text-rose-300'));
        };
        $priorityTone = function ($priority) {
            $priority = strtolower((string) $priority);

            return in_array($priority, ['high', 'alta', '1'], true)
                ? 'badge-danger'
                : (in_array($priority, ['medium', 'media', '2'], true) ? 'badge-warning' : 'badge-success');
        };
    @endphp

    <x-slot name="header">Reporte de Insights</x-slot>

    <div x-data="{ activeTab: 'general' }" class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success no-print shadow-sm">{{ session('success') }}</div>
        @endif

        <!-- Top Actions Bar -->
        <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('insights.index') }}" class="btn-secondary btn-sm w-fit transition duration-200 hover:scale-105 active:scale-95 shadow-sm">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al listado
            </a>
            <button type="button" class="btn-primary btn-sm w-fit transition duration-200 hover:scale-105 active:scale-95 shadow-md" onclick="window.print()">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0h12v4H8v-4z" />
                </svg>
                Imprimir / guardar PDF
            </button>
        </div>

        <!-- Dashboard Header -->
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 transition duration-200 hover:shadow-md">
            <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white px-6 py-6 dark:border-gray-800 dark:from-gray-950 dark:to-gray-900">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                {{ $typeLabel }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                {{ data_get($scope, 'campaign_name', $insight->campaign?->displayName() ?? 'Todas las campañas visibles') }}
                            </span>
                        </div>
                        <h1 class="text-3xl font-bold font-outfit text-gray-900 dark:text-white tracking-tight">Informe ejecutivo de calidad</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5 flex-wrap">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ $insight->date_range_start->format('d/m/Y') }} al {{ $insight->date_range_end->format('d/m/Y') }}
                            <span class="text-gray-300 dark:text-gray-700">·</span>
                            <span>Generado por <strong class="text-gray-700 dark:text-gray-300">{{ $insight->creator->full_name ?? 'Sistema' }}</strong></span>
                            <span class="text-gray-300 dark:text-gray-700">·</span>
                            <span>{{ $insight->created_at->format('d/m/Y H:i') }}</span>
                        </p>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:w-[540px]">
                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition duration-200 hover:scale-[1.02]">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400">Evaluaciones</div>
                            <div class="mt-1.5 text-2xl font-bold font-outfit text-gray-900 dark:text-white">{{ number_format((int) data_get($metrics, 'total_evaluations', 0)) }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition duration-200 hover:scale-[1.02]">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400">Promedio</div>
                            <div class="mt-1.5 text-2xl font-bold font-outfit {{ $scoreTone(data_get($metrics, 'average_score', 0)) }}">{{ number_format((float) data_get($metrics, 'average_score', 0), 1) }}%</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition duration-200 hover:scale-[1.02]">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400">Cumplimiento</div>
                            <div class="mt-1.5 text-2xl font-bold font-outfit text-emerald-600 dark:text-emerald-300">{{ number_format((float) data_get($metrics, 'compliance_rate', 0), 1) }}%</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 shadow-sm transition duration-200 hover:scale-[1.02]">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400">Críticas</div>
                            <div class="mt-1.5 text-2xl font-bold font-outfit text-rose-600 dark:text-rose-300">{{ number_format((int) data_get($metrics, 'critical_failures', 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs (Only visible on screen) -->
            <div class="no-print border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2 flex gap-1.5 overflow-x-auto">
                <button @click="activeTab = 'general'"
                    :class="activeTab === 'general' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm transition-all whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Resumen General
                </button>

                <button @click="activeTab = 'metrics'"
                    :class="activeTab === 'metrics' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm transition-all whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Métricas de Calidad
                </button>

                <button @click="activeTab = 'criteria'"
                    :class="activeTab === 'criteria' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm transition-all whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Criterios Fallidos
                </button>

                <button @click="activeTab = 'action'"
                    :class="activeTab === 'action' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm transition-all whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Oportunidades e IA
                </button>
            </div>

            <!-- Tab Contents Wrapper -->
            <div class="p-6">
                
                <!-- TAB 1: GENERAL EXECUTIVE SUMMARY -->
                <div :class="activeTab === 'general' ? 'block' : 'hidden print:block'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                <h2 class="text-xl font-bold font-outfit text-gray-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Resumen Ejecutivo
                                </h2>
                                <div class="prose prose-indigo prose-sm mt-4 max-w-none dark:prose-invert">
                                    {!! $renderMarkdown($insight->summary_content ?? data_get($findings, 'executive_summary', 'Sin resumen disponible.')) !!}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                    <h2 class="text-lg font-bold font-outfit text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        Lectura para Operaciones
                                    </h2>
                                    <div class="prose prose-sm mt-3 max-w-none dark:prose-invert text-gray-600 dark:text-gray-400">
                                        {!! $renderMarkdown(data_get($findings, 'operations_summary', 'Sin resumen operativo disponible.')) !!}
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                    <h2 class="text-lg font-bold font-outfit text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        Lectura para Cliente
                                    </h2>
                                    <div class="prose prose-sm mt-3 max-w-none dark:prose-invert text-gray-600 dark:text-gray-400">
                                        {!! $renderMarkdown(data_get($findings, 'client_summary', 'Sin resumen para cliente disponible.')) !!}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Interactive Presentation Deck -->
                        <div class="space-y-6">
                            <div class="no-print">
                                <div x-data="{ currentSlide: 0, slides: {{ json_encode($slides->toArray()) }} }" 
                                     class="bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 rounded-2xl border border-slate-800 p-6 text-white relative shadow-xl flex flex-col justify-between min-h-[380px] md:min-h-[440px]">
                                    <!-- Slide Header -->
                                    <div class="flex justify-between items-center border-b border-slate-800/80 pb-3">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></span>
                                            <span class="text-[10px] font-bold tracking-wider text-indigo-400 uppercase font-mono">Presentación Sugerida</span>
                                        </div>
                                        <span class="text-[10px] font-mono text-slate-500" x-text="`Slide ${currentSlide + 1} de ${slides.length}`"></span>
                                    </div>

                                    <!-- Slide Body -->
                                    <div class="my-6 flex-1 flex flex-col justify-center">
                                        <h3 class="text-lg md:text-xl font-bold font-outfit text-white mb-4" x-text="slides[currentSlide].title"></h3>
                                        <ul class="space-y-2.5 text-xs md:text-sm text-slate-300">
                                            <template x-for="(bullet, bIndex) in slides[currentSlide].bullets" :key="bIndex">
                                                <li class="flex items-start gap-2">
                                                    <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span>
                                                    <span x-text="bullet"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <!-- Slide Note -->
                                    <template x-if="slides[currentSlide] && slides[currentSlide].speaker_note">
                                        <div class="mb-4 rounded-xl bg-slate-950/60 p-3.5 text-[11px] text-slate-400 border border-slate-800/50">
                                            <span class="font-bold uppercase tracking-wider text-slate-500 font-mono block mb-1">Notas del presentador:</span>
                                            <p x-text="slides[currentSlide].speaker_note"></p>
                                        </div>
                                    </template>

                                    <!-- Slide Controls -->
                                    <div class="flex justify-between items-center border-t border-slate-800/80 pt-3">
                                        <button @click="currentSlide = Math.max(0, currentSlide - 1)" 
                                                :disabled="currentSlide === 0" 
                                                :class="currentSlide === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-800 text-white'" 
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-800 text-slate-400 transition-all duration-200">
                                            Anterior
                                        </button>
                                        <div class="flex gap-1.5">
                                            <template x-for="(slide, sIndex) in slides" :key="sIndex">
                                                <button @click="currentSlide = sIndex" 
                                                        :class="currentSlide === sIndex ? 'bg-indigo-500 w-5' : 'bg-slate-800 w-1.5 hover:bg-slate-700'" 
                                                        class="h-1.5 rounded-full transition-all duration-200"></button>
                                            </template>
                                        </div>
                                        <button @click="currentSlide = Math.min(slides.length - 1, currentSlide + 1)" 
                                                :disabled="currentSlide === slides.length - 1" 
                                                :class="currentSlide === slides.length - 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-800 text-white'" 
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-800 text-slate-400 transition-all duration-200">
                                            Siguiente
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Print only meeting slides -->
                    <div class="hidden print:block space-y-6">
                        <h2 class="text-xl font-bold font-outfit text-gray-900 border-b pb-2">Guion para Reunión - Slides Sugeridos</h2>
                        <div class="grid grid-cols-2 gap-4">
                            @forelse($slides as $slide)
                                <div class="rounded-xl border border-gray-200 p-5 bg-white page-break-inside-avoid shadow-sm">
                                    <h3 class="font-bold text-base text-gray-800">{{ data_get($slide, 'title') }}</h3>
                                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                                        @foreach(data_get($slide, 'bullets', []) as $bullet)
                                            <li class="flex items-start gap-2">
                                                <span class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span>
                                                <span>{{ $bullet }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if(data_get($slide, 'speaker_note'))
                                        <p class="mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-500">{{ data_get($slide, 'speaker_note') }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Sin guion generado.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- TAB 2: DETAILED QUALITY METRICS -->
                <div :class="activeTab === 'metrics' ? 'block' : 'hidden print:block'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2 space-y-6">
                            
                            <!-- Score Distribution -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                <h2 class="text-lg font-bold font-outfit text-gray-900 dark:text-white mb-4">Distribución de puntajes</h2>
                                <div class="space-y-4">
                                    @forelse($scoreDistribution as $band)
                                        @php($width = round(((int) data_get($band, 'count', 0) / $maxBandCount) * 100))
                                        <div class="group">
                                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                                <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition duration-150">{{ data_get($band, 'label') }}</span>
                                                <span class="font-mono text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 rounded text-xs">{{ number_format((int) data_get($band, 'count', 0)) }} eval.</span>
                                            </div>
                                            <div class="h-3.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 p-0.5 border border-gray-200/40 dark:border-gray-700/40">
                                                <div class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: {{ $width }}%"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin puntajes disponibles.</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Campaign Breakdown Table -->
                            @if($campaignBreakdown->count() > 0)
                                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                    <h2 class="text-lg font-bold font-outfit text-gray-900 dark:text-white mb-4">Detalle por campaña y subcampaña</h2>
                                    <div class="overflow-hidden border border-gray-100 dark:border-gray-800 rounded-xl">
                                        <table class="table">
                                            <thead>
                                                <tr class="bg-gray-50 dark:bg-gray-950">
                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Campaña / Subcampaña</th>
                                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Evaluaciones</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Promedio</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach($campaignBreakdown as $row)
                                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition duration-150">
                                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ data_get($row, 'campaign') }}</td>
                                                        <td class="px-4 py-3 text-center text-sm font-mono text-gray-600 dark:text-gray-400">{{ number_format((int) data_get($row, 'evaluations', 0)) }}</td>
                                                        <td class="px-4 py-3 text-right text-sm font-bold {{ $scoreTone(data_get($row, 'average_score', 0)) }}">{{ number_format((float) data_get($row, 'average_score', 0), 1) }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Right Sidebar: Agent Performance to Review -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm flex flex-col justify-between">
                            <div>
                                <h2 class="text-lg font-bold font-outfit text-gray-900 dark:text-white mb-4">Desempeño a revisar</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Asesores con baja nota o criticidad acumulada en el periodo.</p>
                                <div class="space-y-3">
                                    @forelse($agentPerformance as $agent)
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800/60 dark:bg-gray-950 hover:shadow-sm hover:scale-[1.01] transition-all duration-200">
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ data_get($agent, 'agent') }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                                    <span>{{ data_get($agent, 'evaluations') }} eval.</span>
                                                    <span class="text-gray-300 dark:text-gray-800">•</span>
                                                    <span class="inline-flex items-center text-rose-600 dark:text-rose-400 font-medium font-mono">{{ data_get($agent, 'critical_failures') }} críticas</span>
                                                </div>
                                            </div>
                                            <div class="text-base font-bold font-outfit {{ $scoreTone(data_get($agent, 'average_score', 0)) }} bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border shadow-sm">{{ number_format((float) data_get($agent, 'average_score', 0), 0) }}%</div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin datos por asesor.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: TOP FAILED CRITERIA -->
                <div :class="activeTab === 'criteria' ? 'block' : 'hidden print:block'" class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                        <h2 class="text-xl font-bold font-outfit text-gray-900 dark:text-white mb-2">Criterios con mayor recurrencia de falla</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Puntos críticos y evidencias encontradas con mayor regularidad en el periodo evaluado.</p>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            @forelse($topFailedCriteria as $criteria)
                                <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800 bg-white dark:bg-gray-900/60 shadow-sm flex flex-col justify-between hover:shadow transition duration-200">
                                    <div>
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="space-y-1">
                                                <h3 class="font-bold text-gray-900 dark:text-white leading-snug">{{ data_get($criteria, 'criteria') }}</h3>
                                                <span class="inline-block text-xs font-semibold text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded-md">{{ data_get($criteria, 'category') }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold font-mono {{ data_get($criteria, 'critical') ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300' }}">
                                                    {{ number_format((int) data_get($criteria, 'count', 0)) }} casos
                                                </span>
                                                @if(data_get($criteria, 'critical'))
                                                    <span class="block text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider mt-1.5">Error Crítico</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if(!empty(data_get($criteria, 'examples', [])))
                                            <div class="mt-4 space-y-2.5">
                                                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 block font-mono">Ejemplos de Evidencia:</span>
                                                @foreach(data_get($criteria, 'examples', []) as $example)
                                                    <div class="rounded-xl border-l-4 border-indigo-400 bg-gray-50/70 p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 italic flex gap-2">
                                                        <svg class="w-4 h-4 text-indigo-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                                                        </svg>
                                                        <span>"{{ $example }}"</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-2 text-center py-8">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay criterios fallidos en el periodo.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- TAB 4: OPPORTUNITIES & IA RECOMMENDATIONS -->
                <div :class="activeTab === 'action' ? 'block' : 'hidden print:block'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        
                        <!-- Left Side: AI Opportunities -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                                <h2 class="text-xl font-bold font-outfit text-gray-900 dark:text-white mb-2">Oportunidades de mejora detectadas por IA</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Lecturas analíticas procesadas por inteligencia artificial basadas en el rendimiento del equipo.</p>
                                <div class="space-y-4">
                                    @forelse(data_get($findings, 'improvement_opportunities', []) as $opportunity)
                                        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-950 transition duration-150 hover:shadow-sm">
                                            <div class="flex items-start justify-between gap-3">
                                                <h3 class="font-bold text-gray-900 dark:text-white text-base leading-snug">{{ data_get($opportunity, 'category') }}</h3>
                                                <span class="badge {{ $priorityTone(data_get($opportunity, 'priority')) }} font-bold text-[10px] px-2 py-0.5 uppercase tracking-wide">
                                                    {{ data_get($opportunity, 'priority', 'Prioridad') }}
                                                </span>
                                            </div>
                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ data_get($opportunity, 'description') }}</p>
                                            @if(data_get($opportunity, 'coaching_actions'))
                                                <div class="mt-4 rounded-xl border border-sky-100 bg-sky-50/60 px-4 py-3 text-sm text-sky-800 dark:border-sky-950/40 dark:bg-sky-950/40 dark:text-sky-300 flex items-start gap-2">
                                                    <svg class="w-5 h-5 text-sky-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>
                                                        <strong class="font-bold block mb-0.5">Plan de Acción / Coaching:</strong>
                                                        {{ data_get($opportunity, 'coaching_actions') }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin oportunidades generadas.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Action Plan (Print Friendly List) -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 shadow-sm flex flex-col justify-between">
                            <div>
                                <h2 class="text-xl font-bold font-outfit text-gray-900 dark:text-white mb-2">Recomendaciones del plan</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Estrategias operativas recomendadas con responsables asignados.</p>
                                <div class="space-y-4">
                                    @forelse(data_get($findings, 'recommendations', []) as $recommendation)
                                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-50 font-bold text-xs text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 font-mono">
                                                    {{ data_get($recommendation, 'priority', $loop->iteration) }}
                                                </div>
                                                <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ data_get($recommendation, 'action') }}</h3>
                                            </div>
                                            <p class="mt-2.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ data_get($recommendation, 'expected_impact') }}</p>
                                            <div class="mt-3 flex items-center justify-between border-t border-gray-50 dark:border-gray-800/80 pt-2 text-[10px] uppercase font-bold text-gray-400">
                                                <span>Responsable:</span>
                                                <span class="text-gray-700 dark:text-gray-300 font-mono">{{ data_get($recommendation, 'responsible') }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin recomendaciones generadas.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <style>
        @media print {
            .no-print,
            nav {
                display: none !important;
            }

            body {
                background: #fff !important;
                color: #000 !important;
            }

            main {
                padding: 0 !important;
            }

            .card,
            section {
                box-shadow: none !important;
                border: none !important;
            }

            /* Ensure all tab sections with print:block are forced block layout for print output */
            .print\:block {
                display: block !important;
            }
        }
    </style>
</x-app-layout>
