<x-app-layout>
    <x-slot name="header">{{ $qualityForm->name }}</x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="alert alert-success">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Información General -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Información General</h3>
                <div class="flex items-center gap-2">
                    @if($qualityForm->latestVersion && $qualityForm->latestVersion->status === 'draft')
                        <form method="POST" action="{{ route('quality-forms.publish', $qualityForm) }}">
                            @csrf
                            <button type="submit" class="btn-primary btn-sm">Publicar</button>
                        </form>
                    @endif
                    <a href="{{ route('quality-forms.edit', $qualityForm) }}" class="btn-secondary btn-sm">Editar</a>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Campaña</div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $qualityForm->campaign->name }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Descripción</div>
                        <p class="text-gray-900 dark:text-white">{{ $qualityForm->description ?: 'Sin descripción' }}</p>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Versión Actual</div>
                        @if($qualityForm->latestVersion)
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-white">v{{ $qualityForm->latestVersion->version_number }}</span>
                                @if($qualityForm->latestVersion->status === 'published')
                                    <span class="badge badge-success">Publicada</span>
                                @else
                                    <span class="badge badge-warning">Borrador</span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-400">Sin versión</span>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Creada</div>
                        <p class="text-gray-900 dark:text-white">{{ $qualityForm->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Atributos y Subatributos -->
        @if($qualityForm->latestVersion && $qualityForm->latestVersion->formAttributes->count() > 0)
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                            Estructura de la Ficha
                        </h3>

                        <div class="space-y-6">
                            @foreach($qualityForm->latestVersion->formAttributes as $attribute)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                                    <div class="bg-gray-50 dark:bg-gray-800/50 px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ $attribute->name }}</h4>
                                            @if($attribute->concept)
                                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $attribute->concept }}</p>
                                            @endif
                                        </div>
                                        @php
                                            $allCritical = $attribute->subAttributes->isNotEmpty() && $attribute->subAttributes->every(fn($s) => $s->is_critical);
                                        @endphp
                                        @if($allCritical)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">
                                                Malas Prácticas (Knockout)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300">
                                                Peso: {{ number_format($attribute->weight, 2) }}%
                                            </span>
                                        @endif
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50/50 dark:bg-gray-800/30 text-xs uppercase text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th scope="col" class="px-4 py-2 text-left font-medium tracking-wider w-1/4">Ítem</th>
                                                    <th scope="col" class="px-4 py-2 text-left font-medium tracking-wider w-1/2">Concepto / Guía</th>
                                                    <th scope="col" class="px-4 py-2 text-center font-medium tracking-wider w-24">Peso</th>
                                                    <th scope="col" class="px-4 py-2 text-center font-medium tracking-wider w-24">Crítico</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($attribute->subAttributes as $sub)
                                                    <tr>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white align-top">
                                                            {{ $sub->name }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 align-top">
                                                            @if($sub->concept)
                                                                <div class="mb-1 text-gray-800 dark:text-gray-200">{{ $sub->concept }}</div>
                                                            @endif
                                                            @if($sub->guidelines)
                                                                <div class="text-xs text-gray-400 italic">{{ $sub->guidelines }}</div>
                                                            @endif
                                                            @if(!$sub->concept && !$sub->guidelines)
                                                                <span class="text-gray-300 dark:text-gray-600">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-600 dark:text-gray-300 align-top">
                                                            {{ $sub->weight_percent }}%
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-center align-top">
                                                            @if($sub->is_critical)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">
                                                                    SI
                                                                </span>
                                                            @else
                                                                <span class="text-gray-300 dark:text-gray-600 text-xs">No</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            @php
                                $nonMPWeight = $qualityForm->latestVersion->formAttributes->filter(function($attr) {
                                    $allCritical = $attr->subAttributes->isNotEmpty() && $attr->subAttributes->every(fn($s) => $s->is_critical);
                                    return !$allCritical;
                                })->sum('weight');
                            @endphp
                            <div class="flex justify-between items-center text-sm font-medium">
                                <span class="text-gray-900 dark:text-white">Total Peso Atributos (sin MP):</span>
                                <span class="{{ abs($nonMPWeight - 100) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $nonMPWeight }}%
                                </span>
                            </div>
                            @if(abs($nonMPWeight - 100) > 0.01)
                                <p class="text-xs text-red-500 mt-1">
                                    ¡Atención! La suma de los pesos (sin categorías MP) debe ser exactamente 100%.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="empty-state py-12">
                    <div class="empty-state-icon">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 mb-3">Esta ficha aún no tiene criterios de evaluación</p>
                    <a href="{{ route('quality-forms.edit', $qualityForm) }}" class="btn-primary btn-md">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar Criterios
                    </a>
                </div>
            </div>
        @endif

        <div>
            <a href="{{ route('quality-forms.index') }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Listado
            </a>
        </div>
    </div>
</x-app-layout>
