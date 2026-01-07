<x-app-layout>
    <!-- Custom Background Override -->
    <style>
        .min-h-screen { background-color: #f8fafc !important; } /* Slate 50 - Lighter/Clean */
        .dark .min-h-screen { background-color: #0f172a !important; } /* Slate 900 */
    </style>

    <div x-data="dashboardManager()" x-init="init()" class="space-y-8">
        <!-- Header with Edit Button -->
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Panel de Control</h2>
            <button @click="editMode = !editMode" 
                    class="btn-secondary btn-md flex items-center gap-2"
                    :class="{ 'btn-primary': editMode }">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span x-text="editMode ? 'Finalizar Edición' : 'Personalizar Dashboard'"></span>
            </button>
        </div>
        <!-- Edit Mode Banner -->
        <div x-show="editMode" x-transition class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm text-indigo-700 dark:text-indigo-300">
                        Modo Edición: Arrastra y ajusta tus widgets. Los cambios se guardarán automáticamente.
                    </span>
                </div>
                <button @click="showAddWidget = true" class="btn-primary btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar Widget
                </button>
            </div>
        </div>

        <!-- Default Dashboard (when no widgets) -->
        <div x-show="widgets.length === 0 && !loading" class="text-center py-16">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 12a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Dashboard Vacío</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Empieza agregando widgets personalizados</p>
            <button @click="editMode = true; showAddWidget = true" class="btn-primary btn-md">
                Agregar Primer Widget
            </button>
        </div>

        <!-- Widget Grid -->
        <div x-show="widgets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 auto-rows-max">
            <template x-for="(widget, index) in widgets" :key="widget.id">
                <div :class="{
                        'col-span-1': widget.width === 'sm',
                        'col-span-1 md:col-span-2': widget.width === 'md',
                        'col-span-1 md:col-span-2 lg:col-span-3': widget.width === 'lg',
                        'col-span-1 md:col-span-2 lg:col-span-4': widget.width === 'full',
                        'h-[300px]': widget.widget_type !== 'table', 
                        'h-[400px]': widget.widget_type === 'table'
                     }"
                     class="transition-all duration-300 ease-in-out">
                    
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 h-full flex flex-col hover:shadow-md transition-shadow">
                        <!-- Widget Header -->
                        <div x-show="widget.widget_type !== 'stats_card' || editMode" 
                             class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
                            
                            <!-- Title (or Type for stats) -->
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white truncate max-w-[150px]" x-text="widget.title"></h4>
                            </div>

                            <!-- Edit Controls -->
                            <div class="flex items-center gap-1" x-show="editMode">
                                <!-- Move Left/Up -->
                                <button @click="moveWidget(index, -1)" :disabled="index === 0"
                                        class="p-1 text-gray-400 hover:text-indigo-600 disabled:opacity-30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                
                                <!-- Move Right/Down -->
                                <button @click="moveWidget(index, 1)" :disabled="index === widgets.length - 1"
                                        class="p-1 text-gray-400 hover:text-indigo-600 disabled:opacity-30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                                
                                <div class="h-4 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>

                                <!-- Resize Dropdown -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false" 
                                            class="p-1 text-gray-400 hover:text-indigo-600" title="Cambiar tamaño">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-800 rounded shadow-lg border border-gray-100 z-50 text-xs py-1">
                                        <button @click="updateWidgetSize(widget, 'sm'); open = false" class="block w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Pequeño (1/4)</button>
                                        <button @click="updateWidgetSize(widget, 'md'); open = false" class="block w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Mediano (1/2)</button>
                                        <button @click="updateWidgetSize(widget, 'lg'); open = false" class="block w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Grande (3/4)</button>
                                        <button @click="updateWidgetSize(widget, 'full'); open = false" class="block w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700">Completo (4/4)</button>
                                    </div>
                                </div>

                                <button @click="openConfigModal(widget)" class="p-1 text-gray-400 hover:text-indigo-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
                                
                                <button @click="deleteWidget(widget.id)" class="p-1 text-gray-400 hover:text-rose-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Widget Content -->
                        <div class="flex-1 overflow-hidden relative p-4" x-html="renderWidget(widget)"></div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Add Widget Modal -->
        <div x-show="showAddWidget" @click.away="showAddWidget = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
            <div @click.stop class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-xl fontbold text-gray-900 dark:text-white">Agregar Widget</h3>
                </div>
                <div class="p-6 grid grid-cols-2 gap-4">
                    <!-- Stats Card -->
                    <button @click="addWidget('stats_card', 'Total Evaluaciones')" 
                            class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00 2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">Tarjeta de Métrica</div>
                                <div class="text-sm text-gray-500">Muestra un valor único</div>
                            </div>
                        </div>
                    </button>

                    <!-- Line Chart -->
                    <button @click="addWidget('line_chart', 'Tendencia de Calidad')" 
                            class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">Gráfico de Línea</div>
                                <div class="text-sm text-gray-500">Tendencias en el tiempo</div>
                            </div>
                        </div>
                    </button>

                    <!-- Bar Chart -->
                    <button @click="addWidget('bar_chart', 'Rendimiento por Campaña')" 
                            class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">Gráfico de Barras</div>
                                <div class="text-sm text-gray-500">Comparación de datos</div>
                            </div>
                        </div>
                    </button>

                    <!-- Table -->
                    <button @click="addWidget('table', 'Evaluaciones Recientes')" 
                            class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors text-left">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-rose-100 dark:bg-rose-900/30 rounded-lg">
                                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">Tabla de Datos</div>
                                <div class="text-sm text-gray-500">Lista detallada</div>
                            </div>
                        </div>
                    </button>
                </div>
                <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                    <button @click="showAddWidget = false" class="btn-secondary btn-md">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- Configure Widget Modal -->
        <div x-show="showConfigModal" @click.away="showConfigModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
            <div @click.stop class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full mx-4">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Configurar Widget</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" x-text="configWidget?.title"></p>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Title Configuration (All Widgets) -->
                    <div>
                        <label class="form-label">Título del Widget</label>
                        <input type="text" x-model="configForm.title" class="form-input" placeholder="Ingresa un título personalizado">
                    </div>

                    <!-- Color Picker (For Charts) -->
                    <template x-if="['line_chart', 'bar_chart', 'pie_chart'].includes(configWidget?.widget_type)">
                        <div>
                            <label class="form-label">Color Principal</label>
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="configForm.color" class="w-16 h-10 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                                <span class="text-sm text-gray-500 dark:text-gray-400" x-text="configForm.color || '#4F46E5'"></span>
                                <button @click="configForm.color = '#4F46E5'" class="text-xs text-indigo-600 hover:text-indigo-700">Restablecer</button>
                            </div>
                        </div>
                    </template>

                    <!-- Stats Card Config -->
                    <template x-if="configWidget?.widget_type === 'stats_card'">
                        <div class="space-y-4">
                            <!-- Color Picker for Stats Card -->
                            <div>
                                <label class="form-label">Color del Card</label>
                                <div class="grid grid-cols-6 gap-2">
                                    <button @click="configForm.color = '#4F46E5'" 
                                            class="w-10 h-10 rounded-lg bg-indigo-600 hover:ring-2 ring-indigo-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#4F46E5' }"></button>
                                    <button @click="configForm.color = '#10B981'" 
                                            class="w-10 h-10 rounded-lg bg-emerald-500 hover:ring-2 ring-emerald-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#10B981' }"></button>
                                    <button @click="configForm.color = '#F59E0B'" 
                                            class="w-10 h-10 rounded-lg bg-amber-500 hover:ring-2 ring-amber-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#F59E0B' }"></button>
                                    <button @click="configForm.color = '#EF4444'" 
                                            class="w-10 h-10 rounded-lg bg-rose-500 hover:ring-2 ring-rose-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#EF4444' }"></button>
                                    <button @click="configForm.color = '#8B5CF6'" 
                                            class="w-10 h-10 rounded-lg bg-violet-500 hover:ring-2 ring-violet-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#8B5CF6' }"></button>
                                    <button @click="configForm.color = '#06B6D4'" 
                                            class="w-10 h-10 rounded-lg bg-cyan-500 hover:ring-2 ring-cyan-400 transition-all"
                                            :class="{ 'ring-2 ring-offset-2': configForm.color === '#06B6D4' }"></button>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="color" x-model="configForm.color" class="w-12 h-10 rounded border cursor-pointer">
                                    <span class="text-xs text-gray-500" x-text="configForm.color"></span>
                                </div>
                            </div>

                            <!-- Icon Selection -->
                            <div>
                                <label class="form-label">Icono</label>
                                <select x-model="configForm.icon" class="form-select">
                                    <option value="chart">📊 Gráfico</option>
                                    <option value="target">🎯 Objetivo</option>
                                    <option value="trophy">🏆 Trofeo</option>
                                    <option value="star">⭐ Estrella</option>
                                    <option value="fire">🔥 Fuego</option>
                                    <option value="rocket">🚀 Cohete</option>
                                    <option value="lightning">⚡ Rayo</option>
                                    <option value="users">👥 Usuarios</option>
                                </select>
                            </div>

                            <!-- Metric Selection -->
                            <div>
                                <label class="form-label">Métrica a Mostrar</label>
                                <select x-model="configForm.metric" class="form-select">
                                    <option value="total_evaluations">Total de Evaluaciones</option>
                                    <option value="avg_score">Promedio de Calidad</option>
                                    <option value="pending_disputes">Disputas Pendientes</option>
                                    <option value="active_campaigns">Campañas Activas</option>
                                    <option value="total_agents">Total de Agentes</option>
                                    <option value="evaluations_this_month">Evaluaciones Este Mes</option>
                                    <option value="compliance_rate">Tasa de Cumplimiento</option>
                                    <option value="response_rate">Tasa de Respuesta</option>
                                    <option value="avg_resolution_time">Tiempo Prom. Resolución</option>
                                    <option value="top_performer">Mejor Agente (30d)</option>
                                    <option value="worst_performer">Peor Agente (30d)</option>
                                </select>
                            </div>

                            <!-- Comparison Toggle -->
                            <div class="flex items-center gap-2">
                                <input type="checkbox" x-model="configForm.show_comparison" class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                                <label class="form-label mb-0">Comparar con periodo anterior</label>
                            </div>

                            <!-- Target/Goal -->
                            <div>
                                <label class="form-label">Objetivo/Meta (opcional)</label>
                                <input type="number" x-model="configForm.target" class="form-input" placeholder="Ej: 100">
                                <p class="text-xs text-gray-500 mt-1">Define una meta para ver el progreso</p>
                            </div>
                        </div>
                    </template>

                    <!-- Line/Bar Chart Config -->
                    <template x-if="['line_chart', 'bar_chart'].includes(configWidget?.widget_type)">
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Métrica</label>
                                <select x-model="configForm.metric" class="form-select">
                                    <option value="campaign_performance">Rendimiento por Campaña</option>
                                    <option value="agent_performance">Rendimiento por Agente</option>
                                    <option value="avg_score">Promedio de Calidad</option>
                                    <option value="count">Cantidad de Evaluaciones</option>
                                </select>
                            </div>
                            
                            <div x-show="configWidget?.widget_type === 'line_chart'">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label">Rango de Días</label>
                                        <select x-model="configForm.days" class="form-select">
                                            <option value="7">Últimos 7 días</option>
                                            <option value="15">Últimos 15 días</option>
                                            <option value="30">Últimos 30 días</option>
                                            <option value="60">Últimos 60 días</option>
                                            <option value="90">Últimos 90 días</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Agrupar por</label>
                                        <select x-model="configForm.group_by" class="form-select">
                                            <option value="hour">Hora</option>
                                            <option value="day">Día</option>
                                            <option value="week">Semana</option>
                                            <option value="month">Mes</option>
                                            <option value="year">Año</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Table Config -->
                    <template x-if="configWidget?.widget_type === 'table'">
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Tipo de Tabla</label>
                                <select x-model="configForm.type" class="form-select">
                                    <option value="recent_evaluations">Evaluaciones Recientes</option>
                                    <option value="top_agents">Top 10 Agentes</option>
                                    <option value="bottom_agents">Bolttom 10 Agentes</option>
                                    <option value="disputed_items">Items en Disputa</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Cantidad de Filas</label>
                                <select x-model="configForm.limit" class="form-select">
                                    <option value="5">5 filas</option>
                                    <option value="10">10 filas</option>
                                    <option value="15">15 filas</option>
                                    <option value="20">20 filas</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
                    <button @click="showConfigModal = false" class="btn-secondary btn-md">Cancelar</button>
                    <button @click="saveConfig()" class="btn-primary btn-md">Guardar Cambios</button>
                </div>
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        function dashboardManager() {
            return {
                widgets: [],
                editMode: false,
                showAddWidget: false,
                showConfigModal: false,
                configWidget: null,
                configForm: {},
                availableColumns: [], // New property
                loading: true,
                grid: null,
                widgetData: {},
                charts: {},

                init() {
                    console.log('Dashboard initializing...');
                    this.loadWidgets();
                },

                updateAvailableColumns() { // New method
                    const type = this.configForm.type || 'recent_evaluations';
                    const columnsMap = {
                        'recent_evaluations': ['ID', 'Fecha', 'Campaña', 'Agente', 'Puntaje', 'Estado'],
                        'top_agents': ['Agente', 'Promedio', 'Evaluaciones'],
                        'bottom_agents': ['Agente', 'Promedio', 'Evaluaciones'],
                        'disputed_items': ['Fecha', 'Agente', 'Motivo', 'Estado']
                    };
                    
                    this.availableColumns = columnsMap[type] || [];
                    
                    // Reset visible columns if not set or if type changes
                    if (!this.configForm.visible_columns || this.configForm.visible_columns.length === 0 || !this.availableColumns.every(col => this.configForm.visible_columns.includes(col))) {
                        this.configForm.visible_columns = [...this.availableColumns];
                    }
                },

                async loadWidgets() {
                    try {
                        const response = await fetch('{{ route("dashboard.widgets.index") }}');
                        const widgets = await response.json();
                        
                        // Sort by order
                        this.widgets = widgets.sort((a, b) => a.sort_order - b.sort_order);
                        
                        this.loading = false;
                        
                        if (this.widgets.length > 0) {
                            // Render contents
                            this.$nextTick(async () => {
                                for (const widget of this.widgets) {
                                    await this.fetchWidgetData(widget);
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Error loading widgets:', error);
                        this.loading = false;
                    }
                },

                async fetchWidgetData(widget) {
                    try {
                        console.log('Fetching data for widget:', widget.id, widget.widget_type);
                        const response = await fetch('{{ route("dashboard.widgets.data") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                widget_type: widget.widget_type,
                                config: widget.config
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to fetch widget data');
                        }

                        const data = await response.json();
                        
                        // Force Alpine reactivity by creating new object
                        this.widgetData = {
                            ...this.widgetData,
                            [widget.id]: data
                        };
                        
                        this.$nextTick(() => {
                            this.renderWidgetContent(widget, data);
                        });
                    } catch (error) {
                        console.error(`Error fetching data for widget ${widget.id}:`, error);
                        // Set error state to remove spinner
                        this.widgetData = {
                            ...this.widgetData,
                            [widget.id]: { error: true, message: 'Error al cargar datos' }
                        };
                    }
                },

                async addWidget(type, title) {
                    // 1. Create Optimistic Widget (Temp ID)
                    const tempId = 'temp-' + Date.now();
                    const optimisticWidget = {
                        id: tempId,
                        widget_type: type,
                        title: title,
                        config: this.getDefaultConfig(type),
                        width: type === 'table' ? 'full' : 'sm',
                        sort_order: this.widgets.length,
                        is_optimistic: true
                    };

                    // 2. Render Immediately
                    this.widgets.push(optimisticWidget);
                    this.showAddWidget = false;
                    
                    // 3. Send Request in Background
                    try {
                        const response = await fetch('{{ route("dashboard.widgets.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                widget_type: type,
                                title: title,
                                config: optimisticWidget.config,
                                width: optimisticWidget.width,
                                sort_order: optimisticWidget.sort_order
                            })
                        });

                        if (!response.ok) throw new Error('Failed to create widget');

                        const realWidget = await response.json();
                        
                        // 4. Swap Temp Widget with Real Widget
                        const index = this.widgets.findIndex(w => w.id === tempId);
                        if (index !== -1) {
                            // Preserve any local state if needed (none really for new widgets)
                            this.widgets[index] = realWidget;
                            
                            // 5. Fetch Data for the Real Widget
                            this.$nextTick(() => {
                                this.fetchWidgetData(realWidget);
                            });
                        }
                    } catch (error) {
                        console.error('Error adding widget:', error);
                        // Rollback on error
                        this.widgets = this.widgets.filter(w => w.id !== tempId);
                        alert('Error al crear el widget. Por favor intenta de nuevo.');
                    }
                },

                getDefaultConfig(type) {
                    const configs = {
                        stats_card: { metric: 'total_evaluations', color: '#4F46E5', icon: 'chart', target: null },
                        line_chart: { metric: 'avg_score', days: 30, color: '#4F46E5' },
                        bar_chart: { metric: 'campaign_performance', color: '#4F46E5' },
                        pie_chart: { metric: 'status_distribution' },
                        table: { type: 'recent_evaluations', limit: 10 }
                    };
                    return configs[type] || {};
                },

                async deleteWidget(widgetId) {
                    if (!confirm('¿Eliminar este widget?')) return;

                    try {
                        await fetch(`/dashboard/widgets/${widgetId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        this.widgets = this.widgets.filter(w => w.id !== widgetId);
                        
                        // Destroy chart if exists
                        if (this.charts[widgetId]) {
                            this.charts[widgetId].destroy();
                            delete this.charts[widgetId];
                        }
                    } catch (error) {
                        console.error('Error deleting widget:', error);
                    }
                },

                openConfigModal(widget) {
                    this.configWidget = widget;
                    this.configForm = { 
                        title: widget.title,
                        color: widget.config.color || '#4F46E5',
                        show_comparison: widget.config.show_comparison || false,
                        group_by: widget.config.group_by || 'day',
                        days: widget.config.days || 30,
                        type: widget.config.type || 'recent_evaluations',
                        visible_columns: widget.config.visible_columns || [],
                        ...widget.config 
                    };
                    
                    if (widget.widget_type === 'table') {
                        this.updateAvailableColumns();
                    }
                    
                    this.showConfigModal = true;
                },

                async saveConfig() {
                    try {
                        const { title, color, ...config } = this.configForm;
                        const widgetId = this.configWidget.id;
                        const widgetType = this.configWidget.widget_type;
                        
                        // Destroy existing chart if it's a chart widget
                        if (['line_chart', 'bar_chart', 'pie_chart'].includes(widgetType)) {
                            if (this.charts[widgetId]) {
                                console.log('Destroying chart:', widgetId);
                                this.charts[widgetId].destroy();
                                delete this.charts[widgetId];
                            }
                        }
                        
                        const response = await fetch(`/dashboard/widgets/${widgetId}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                title: title,
                                config: { ...config, color: color }
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to save configuration');
                        }

                        const updatedWidget = await response.json();
                        console.log('Widget updated:', updatedWidget);
                        
                        // Update widget in array (trigger Alpine reactivity)
                        const index = this.widgets.findIndex(w => w.id === updatedWidget.id);
                        if (index !== -1) {
                            // Force reactivity by creating new array
                            this.widgets = [
                                ...this.widgets.slice(0, index),
                                updatedWidget,
                                ...this.widgets.slice(index + 1)
                            ];
                        }

                        // Clear old widget data
                        delete this.widgetData[widgetId];

                        // Refetch data with new config
                        await this.fetchWidgetData(updatedWidget);
                        
                        // Force UI update for the specific widget
                        this.$nextTick(() => {
                            const widgetEl = document.querySelector(`[data-widget-id="${widgetId}"]`);
                            if (widgetEl) {
                                // Trigger a re-render by touching Alpine's reactive system
                                const temp = this.widgetData[widgetId];
                                this.widgetData[widgetId] = null;
                                this.$nextTick(() => {
                                    this.widgetData[widgetId] = temp;
                                });
                            }
                        });
                        
                        this.showConfigModal = false;
                        
                        console.log('Config saved successfully');
                    } catch (error) {
                        console.error('Error saving config:', error);
                        alert('Error al guardar la configuración. Por favor intenta de nuevo.');
                    }
                },

                async moveWidget(index, direction) {
                    const newIndex = index + direction;
                    if (newIndex < 0 || newIndex >= this.widgets.length) return;

                    // Swap in array
                    const temp = this.widgets[index];
                    this.widgets[index] = this.widgets[newIndex];
                    this.widgets[newIndex] = temp;

                    // Update sort_order for all widgets to be safe
                    const updates = this.widgets.map((w, i) => ({
                        id: w.id,
                        sort_order: i
                    }));

                    // Optimistic UI update
                    // this.widgets is already updated

                    try {
                        await fetch('{{ route("dashboard.widgets.bulk-update") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ widgets: updates })
                        });
                    } catch (error) {
                        console.error('Error moving widget:', error);
                        // Revert on error? For now just log
                    }
                },

                async updateWidgetSize(widget, size) {
                    widget.width = size;
                    
                    try {
                        await fetch(`/dashboard/widgets/${widget.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                title: widget.title,
                                width: size,
                                sort_order: widget.sort_order, // Ensure we keep order
                                config: widget.config
                            })
                        });
                        
                        // Force chart resize if needed
                        if (['line_chart', 'bar_chart', 'pie_chart'].includes(widget.widget_type)) {
                            this.renderWidgetContent(widget, this.widgetData[widget.id]);
                        }
                    } catch (error) {
                        console.error('Error updating widget size:', error);
                    }
                },

                // Method removed: savePositions (no longer needed for strict grid)

                renderWidget(widget) {
                    const data = this.widgetData[widget.id];
                    
                    if (!data) {
                        return `<div class="flex items-center justify-center h-full">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                        </div>`;
                    }

                    if (data.error) {
                        return `<div class="flex flex-col items-center justify-center h-full text-red-500 p-4 text-center">
                            <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs">${data.message}</span>
                            <button onclick="document.getElementById('retry-${widget.id}').click()" class="mt-2 text-xs underline cursor-pointer">Reintentar</button>
                            <button id="retry-${widget.id}" class="hidden" @click="fetchWidgetData(widgets.find(w => w.id === ${widget.id}))"></button>
                        </div>`;
                    }

                    switch (widget.widget_type) {
                        case 'stats_card':
                            return this.renderStatsCard(data);
                        case 'line_chart':
                        case 'bar_chart':
                        case 'pie_chart':
                            return `<canvas id="chart-${widget.id}" class="w-full h-full"></canvas>`;
                        case 'table':
                            return this.renderTable(data);
                        default:
                            return '<div class="text-gray-500">Widget desconocido</div>';
                    }
                },

                renderStatsCard(data) {
                    const widget = this.widgets.find(w => this.widgetData[w.id] === data);
                    const config = widget?.config || {};
                    const color = config.color || '#4F46E5';
                    const target = config.target;
                    const icon = config.icon || 'chart';
                    
                    const metricLabels = {
                        total_evaluations: 'Total Evaluaciones',
                        avg_score: 'Promedio de Calidad',
                        pending_disputes: 'Disputas Pendientes',
                        active_campaigns: 'Campañas Activas',
                        total_agents: 'Total de Agentes',
                        evaluations_this_month: 'Evaluaciones Este Mes',
                        compliance_rate: 'Tasa de Cumplimiento',
                        response_rate: 'Tasa de Respuesta',
                        avg_resolution_time: 'Tiempo Prom. Resolución',
                        top_performer: 'Mejor Agente (30d)',
                        worst_performer: 'Peor Agente (30d)'
                    };

                    const iconMap = {
                        chart: '📊',
                        target: '🎯',
                        trophy: '🏆',
                        star: '⭐',
                        fire: '🔥',
                        rocket: '🚀',
                        lightning: '⚡',
                        users: '👥'
                    };

                    const label = metricLabels[data.metric] || data.metric;
                    const value = data.value;
                    const suffix = data.metric === 'avg_score' || data.metric === 'compliance_rate' || data.metric === 'response_rate' ? '%' 
                                 : data.metric === 'avg_resolution_time' ? ' días' 
                                 : '';
                    const displayIcon = iconMap[icon] || '📊';
                    const isNameMetric = data.metric === 'top_performer' || data.metric === 'worst_performer';
                    
                    // Determine text size based on metric type
                    const valueTextSize = isNameMetric ? 'text-3xl' : 'text-6xl';

                    // Calculate progress if target exists
                    const hasTarget = target && target > 0;
                    const progress = hasTarget ? Math.min((value / target) * 100, 100) : 0;
                    
                    // Comparison Badge
                    const comparison = data.comparison;
                    let comparisonHtml = '';
                    if (comparison) {
                        const isUp = comparison.direction === 'up';
                        const colorClass = isUp ? 'text-green-600 bg-green-50 dark:bg-green-900/30 dark:text-green-400' : 'text-red-600 bg-red-50 dark:bg-red-900/30 dark:text-red-400';
                        const arrow = isUp ? '↑' : '↓';
                        comparisonHtml = `
                            <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${colorClass} mb-2">
                                <span class="mr-1">${arrow}</span> ${Math.abs(comparison.change)}% vs periodo anterior
                            </div>
                        `;
                    }

                    return `
                        <div class="h-full w-full flex flex-col justify-center p-6 rounded-lg relative overflow-hidden"
                             style="background: linear-gradient(135deg, ${color}15 0%, ${color}08 100%);">
                            
                            <!-- Background Icon -->
                            <div class="absolute top-3 right-3 text-5xl opacity-10">
                                ${displayIcon}
                            </div>
                            
                            <div class="relative z-10">
                                <!-- Title -->
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl opacity-80">${displayIcon}</span>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        ${widget.title || label}
                                    </h3>
                                </div>
                                
                                <!-- Comparison -->
                                ${comparisonHtml}
                                
                                <!-- Value -->
                                <div class="${valueTextSize} font-black mb-3 leading-none" 
                                     style="color: ${color};">
                                    ${value}${suffix}
                                </div>
                                
                                ${hasTarget ? `
                                    <!-- Target Info -->
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        Meta: <strong>${target}${suffix}</strong>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-1.5">
                                        <div class="h-full rounded-full transition-all duration-700" 
                                             style="width: ${progress}%; background: ${color};"></div>
                                    </div>
                                    
                                    <!-- Progress Percentage -->
                                    <div class="text-sm font-bold" style="color: ${color};">
                                        ${Math.round(progress)}% completado
                                    </div>
                                ` : ''}
                            </div>
                            
                            <!-- Decorative Circle -->
                            <div class="absolute -bottom-6 -right-6 w-24 h-24 rounded-full opacity-10" 
                                 style="background: ${color};"></div>
                        </div>
                    `;
                },

                renderTable(data) {
                    if (!data.rows || data.rows.length === 0) {
                        return '<div class="text-center text-gray-500 py-8">No hay datos</div>';
                    }

                    let html = '<div class="overflow-auto h-full"><table class="w-full text-sm">';
                    html += '<thead class="bg-gray-50 dark:bg-gray-700"><tr>';
                    data.columns.forEach(col => {
                        html += `<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">${col}</th>`;
                    });
                    html += '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">';
                    
                    data.rows.forEach(row => {
                        html += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800">';
                        row.forEach(cell => {
                            html += `<td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">${cell}</td>`;
                        });
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    return html;
                },

                renderWidgetContent(widget, data) {
                    if (['line_chart', 'bar_chart', 'pie_chart'].includes(widget.widget_type)) {
                        // Destroy existing chart first
                        if (this.charts[widget.id]) {
                            this.charts[widget.id].destroy();
                            delete this.charts[widget.id];
                        }
                        
                        // Use requestAnimationFrame for better render timing
                        requestAnimationFrame(() => {
                            const canvas = document.getElementById(`chart-${widget.id}`);
                            
                            // Safety Check: Canvas must exist and be connected to DOM
                            if (!canvas || !canvas.isConnected) {
                                return;
                            }

                            // Ensure container has size
                            if (canvas.parentElement) {
                                canvas.parentElement.style.position = 'relative';
                                canvas.parentElement.style.height = '100%';
                                canvas.parentElement.style.width = '100%';
                            }

                            const ctx = canvas.getContext('2d');
                            if (ctx) {
                                const chartConfig = this.getChartConfig(widget.widget_type, data, widget);
                                // Force responsive true
                                chartConfig.options = { 
                                    ...chartConfig.options, 
                                    responsive: true, 
                                    maintainAspectRatio: false,
                                    resizeDelay: 200 // Debounce resize
                                };
                                this.charts[widget.id] = new Chart(ctx, chartConfig);
                            }
                        });
                    }
                },

                getChartConfig(type, data, widget) {
                    // Accept widget as parameter or find it
                    if (!widget) {
                        widget = this.widgets.find(w => this.widgetData[w.id] === data);
                    }
                    const color = widget?.config?.color || '#4F46E5';
                    
                    const commonOptions = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: type === 'pie_chart',
                                labels: {
                                    color: document.documentElement.classList.contains('dark') ? '#9CA3AF' : '#1F2937'
                                }
                            }
                        }
                    };

                    if (type === 'line_chart') {
                        return {
                            type: 'line',
                            data: {
                                ...data,
                                datasets: data.datasets.map(ds => ({
                                    ...ds,
                                    borderColor: color,
                                    backgroundColor: color + '20',
                                    tension: 0.4
                                }))
                            },
                            options: {
                                ...commonOptions,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { color: '#9CA3AF' },
                                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                                    },
                                    x: {
                                        ticks: { color: '#9CA3AF' },
                                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                                    }
                                }
                            }
                        };
                    }

                    if (type === 'bar_chart') {
                        return {
                            type: 'bar',
                            data: {
                                ...data,
                                datasets: data.datasets.map(ds => ({
                                    ...ds,
                                    backgroundColor: color,
                                    borderColor: color,
                                    borderWidth: 1
                                }))
                            },
                            options: {
                                ...commonOptions,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { color: '#9CA3AF' },
                                        grid: { color: 'rgba(156, 163, 175, 0.1)' }
                                    },
                                    x: {
                                        ticks: { color: '#9CA3AF' },
                                        grid: { display: false }
                                    }
                                }
                            }
                        };
                    }

                    if (type === 'pie_chart') {
                        return {
                            type: 'pie',
                            data: data,
                            options: commonOptions
                        };
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
