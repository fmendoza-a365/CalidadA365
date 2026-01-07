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
        <div x-show="widgets.length > 0" class="grid-stack" id="widget-grid">
            <template x-for="widget in widgets" :key="widget.id">
                <div class="grid-stack-item" 
                     :data-widget-id="widget.id"
                     :gs-id="widget.id"
                     :gs-x="widget.position_x" 
                     :gs-y="widget.position_y"
                     :gs-w="widget.width" 
                     :gs-h="widget.height">
                    <div class="grid-stack-item-content">
                        <!-- Widget Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 h-full flex flex-col">
                            <!-- Widget Header (only for non-stats cards) -->
                            <div x-show="widget.widget_type !== 'stats_card'" 
                                 class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
                                <h4 class="font-semibold text-gray-900 dark:text-white" x-text="widget.title"></h4>
                                <div class="flex items-center gap-2" x-show="editMode">
                                    <button @click="openConfigModal(widget)" 
                                            class="text-gray-400 hover:text-indigo-600 transition-colors"
                                            title="Configurar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    <button @click="deleteWidget(widget.id)" 
                                            class="text-gray-400 hover:text-rose-600 transition-colors"
                                            title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Edit buttons for stats cards (floating) -->
                            <div x-show="widget.widget_type === 'stats_card' && editMode" 
                                 class="absolute top-2 right-2 z-20 flex items-center gap-1">
                                <button @click="openConfigModal(widget)" 
                                        class="p-1.5 bg-white dark:bg-gray-800 rounded-lg shadow-md text-gray-400 hover:text-indigo-600 transition-colors"
                                        title="Configurar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
                                <button @click="deleteWidget(widget.id)" 
                                        class="p-1.5 bg-white dark:bg-gray-800 rounded-lg shadow-md text-gray-400 hover:text-rose-600 transition-colors"
                                        title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Widget Content -->
                            <div class="flex-1 overflow-auto" x-html="renderWidget(widget)"></div>
                        </div>
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
                                <label class="form-label">Rango de Días</label>
                                <select x-model="configForm.days" class="form-select">
                                    <option value="7">Últimos 7 días</option>
                                    <option value="15">Últimos 15 días</option>
                                    <option value="30">Últimos 30 días</option>
                                    <option value="60">Últimos 60 días</option>
                                    <option value="90">Últimos 90 días</option>
                                </select>
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
    <!-- Gridstack.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@latest/dist/gridstack.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/gridstack@latest/dist/gridstack-all.js"></script>
    
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
                loading: true,
                grid: null,
                widgetData: {},
                charts: {},

                init() {
                    console.log('Dashboard initializing...');
                    this.loadWidgets();
                },

                async loadWidgets() {
                    try {
                        const response = await fetch('{{ route("dashboard.widgets.index") }}');
                        this.widgets = await response.json();
                        this.loading = false;
                        
                        this.$nextTick(() => {
                            this.initGrid();
                            this.loadAllWidgetData();
                        });
                    } catch (error) {
                        console.error('Error loading widgets:', error);
                        this.loading = false;
                    }
                },

                initGrid() {
                    console.log('Initializing GridStack...');
                    if (this.grid) {
                        console.log('Grid already exists, destroying...');
                        this.grid.destroy(false);
                    }
                    
                    this.grid = GridStack.init({
                        cellHeight: 80,
                        animate: true,
                        float: false,
                        minRow: 1,
                        column: 12,
                        margin: 8,
                        disableDrag: !this.editMode,
                        disableResize: !this.editMode,
                    });

                    console.log('GridStack initialized:', this.grid);

                    // Load existing widgets into grid
                    this.$nextTick(() => {
                        this.widgets.forEach(widget => {
                            const el = document.querySelector(`[data-widget-id="${widget.id}"]`);
                            if (el && !el.gridstackNode) {
                                console.log('Adding widget to grid:', widget.id, {
                                    x: widget.position_x,
                                    y: widget.position_y,
                                    w: widget.width,
                                    h: widget.height
                                });
                                this.grid.makeWidget(el);
                            }
                        });
                    });

                    // Watch editMode changes
                    this.$watch('editMode', (value) => {
                        console.log('Edit mode changed to:', value);
                        if (this.grid) {
                            if (value) {
                                this.grid.enable();
                                console.log('GridStack enabled for editing');
                            } else {
                                this.grid.disable();
                                console.log('GridStack disabled');
                            }
                        }
                    });

                    this.grid.on('change', (event, items) => {
                        console.log('GridStack change event:', items);
                        if (this.editMode && items && items.length > 0) {
                            this.savePositions(items);
                        }
                    });
                },

                async loadAllWidgetData() {
                    for (const widget of this.widgets) {
                        await this.fetchWidgetData(widget);
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
                        console.log('Data fetched for widget:', widget.id, data);
                        
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
                        alert('Error al cargar datos del widget. Por favor recarga la página.');
                    }
                },

                async addWidget(type, title) {
                    const newWidget = {
                        widget_type: type,
                        title: title,
                        config: this.getDefaultConfig(type),
                        position_x: 0,
                        position_y: 0,
                        width: type === 'stats_card' ? 3 : 6,
                        height: type === 'table' ? 4 : type === 'stats_card' ? 2 : 3,
                        order: this.widgets.length
                    };

                    try {
                        const response = await fetch('{{ route("dashboard.widgets.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(newWidget)
                        });

                        const widget = await response.json();
                        this.widgets.push(widget);
                        this.showAddWidget = false;
                        
                        this.$nextTick(() => {
                            const el = document.querySelector(`[gs-id="${widget.id}"]`);
                            if (el) {
                                this.grid.makeWidget(el);
                                this.fetchWidgetData(widget);
                            }
                        });
                    } catch (error) {
                        console.error('Error adding widget:', error);
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
                        
                        const el = document.querySelector(`[gs-id="${widgetId}"]`);
                        if (el && this.grid) {
                            this.grid.removeWidget(el);
                        }

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
                        ...widget.config 
                    };
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
                        
                        this.showConfigModal = false;
                        
                        console.log('Config saved successfully');
                    } catch (error) {
                        console.error('Error saving config:', error);
                        alert('Error al guardar la configuración. Por favor intenta de nuevo.');
                    }
                },

                async savePositions(items) {
                    const updates = items.map(item => {
                        const id = parseInt(item.el.getAttribute('gs-id'));
                        return {
                            id: id,
                            position_x: item.x,
                            position_y: item.y,
                            width: item.w,
                            height: item.h
                        };
                    });

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
                        console.error('Error saving positions:', error);
                    }
                },

                renderWidget(widget) {
                    const data = this.widgetData[widget.id];
                    
                    if (!data) {
                        return `<div class="flex items-center justify-center h-full">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
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

                    // Calculate progress if target exists
                    const hasTarget = target && target > 0;
                    const progress = hasTarget ? Math.min((value / target) * 100, 100) : 0;

                    return `
                        <div class="h-full w-full flex flex-col justify-center p-6 rounded-lg relative overflow-hidden"
                             style="background: linear-gradient(135deg, ${color}15 0%, ${color}08 100%);">
                            
                            <!-- Background Icon -->
                            <div class="absolute top-3 right-3 text-5xl opacity-10">
                                ${displayIcon}
                            </div>
                            
                            <!-- Content -->
                            <div class="relative z-10">
                                <!-- Label -->
                                <div class="text-xs font-semibold uppercase tracking-wider mb-2 opacity-75" 
                                     style="color: ${color};">
                                    ${label}
                                </div>
                                
                                <!-- Value -->
                                <div class="text-6xl font-black mb-3 leading-none" 
                                     :class="isNameMetric ? 'text-3xl' : ''"
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
                        this.$nextTick(() => {
                            // Wait a bit more for DOM to be ready
                            setTimeout(() => {
                                const canvas = document.getElementById(`chart-${widget.id}`);
                                if (canvas) {
                                    console.log('Rendering chart:', widget.id, widget.widget_type);
                                    
                                    // Destroy existing chart if any
                                    if (this.charts[widget.id]) {
                                        console.log('Destroying existing chart:', widget.id);
                                        this.charts[widget.id].destroy();
                                    }

                                    const ctx = canvas.getContext('2d');
                                    const chartConfig = this.getChartConfig(widget.widget_type, data, widget);
                                    
                                    this.charts[widget.id] = new Chart(ctx, chartConfig);
                                    console.log('Chart created successfully:', widget.id);
                                } else {
                                    console.error('Canvas not found for widget:', widget.id);
                                }
                            }, 100);
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
