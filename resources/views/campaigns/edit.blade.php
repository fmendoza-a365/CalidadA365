<x-app-layout>
    <x-slot name="header">Editar Campaña: {{ $campaign->name }}</x-slot>

    <div class="max-w-7xl mx-auto py-8">
        <form method="POST" action="{{ route('campaigns.update', $campaign) }}" enctype="multipart/form-data" 
              class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            @csrf
            @method('PUT')

            <!-- Left Column: Identity -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Logo & Style Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Identidad Visual</h3>
                    
                    <!-- Logo Upload -->
                    <div x-data="{ logoPreview: '{{ $campaign->logo_url }}' }" class="space-y-4 mb-6">
                        <div class="flex flex-col items-center">
                            <!-- Preview Circle -->
                            <div class="relative w-32 h-32 mb-4 group">
                                <template x-if="!logoPreview">
                                    <div class="w-full h-full rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" rx="0" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </template>
                                <template x-if="logoPreview">
                                    <img :src="logoPreview" class="w-full h-full rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-md">
                                </template>

                                <!-- Upload Button Overlay -->
                                <label for="logo" class="absolute bottom-0 right-0 p-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full cursor-pointer shadow-lg transition-transform transform hover:scale-110">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <input type="file" name="logo" id="logo" class="hidden" accept="image/*"
                                           @change="const file = $event.target.files[0]; if(file) { const reader = new FileReader(); reader.onload = (e) => logoPreview = e.target.result; reader.readAsDataURL(file); }">
                                </label>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Actualizar Logo (Max 2MB)</span>
                            <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                        </div>
                    </div>

                    <!-- Color Picker -->
                    <div class="mb-6">
                        <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Color Distintivo</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="color" id="color" value="{{ old('color', $campaign->color) }}" class="h-10 w-20 p-1 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                            <span class="text-gray-500 text-sm">Identifica esta campaña</span>
                        </div>
                         <x-input-error :messages="$errors->get('color')" class="mt-1" />
                    </div>
                </div>

                <!-- Basic Info Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                     <div class="form-group mb-4">
                        <label for="name" class="form-label">Nombre <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $campaign->name) }}" 
                            class="form-input" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="form-group mb-4">
                        <label for="type" class="form-label">Tipo de Campaña <span class="text-rose-500">*</span></label>
                        <select name="type" id="type" class="form-select">
                            <option value="Inbound" {{ old('type', $campaign->type) == 'Inbound' ? 'selected' : '' }}>Inbound (Entrante)</option>
                            <option value="Outbound" {{ old('type', $campaign->type) == 'Outbound' ? 'selected' : '' }}>Outbound (Saliente)</option>
                            <option value="Blended" {{ old('type', $campaign->type) == 'Blended' ? 'selected' : '' }}>Blended (Híbrida)</option>
                            <option value="Backoffice" {{ old('type', $campaign->type) == 'Backoffice' ? 'selected' : '' }}>Backoffice / Administrativo</option>
                            <option value="Chat/Email" {{ old('type', $campaign->type) == 'Chat/Email' ? 'selected' : '' }}>Digital (Chat/Email/Redes)</option>
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-1" />
                    </div>

                     <div class="form-group">
                        <label class="flex items-center gap-3 cursor-pointer p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                class="form-checkbox h-5 w-5 text-indigo-600" {{ old('is_active', $campaign->is_active) ? 'checked' : '' }}>
                            <span class="font-medium text-gray-700 dark:text-gray-300">Campaña Activa</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Operational Targets Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Objetivos y Metas (KPIs)
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="target_quality" class="form-label">Meta de Calidad (%)</label>
                            <div class="relative">
                                <input type="number" name="target_quality" id="target_quality" value="{{ old('target_quality', $campaign->target_quality) }}" 
                                    class="form-input pr-8" step="0.01" min="0" max="100">
                                <span class="absolute right-3 top-2.5 text-gray-400">%</span>
                            </div>
                            <x-input-error :messages="$errors->get('target_quality')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="target_aht" class="form-label">Meta AHT (Segundos)</label>
                            <input type="number" name="target_aht" id="target_aht" value="{{ old('target_aht', $campaign->target_aht) }}" 
                                class="form-input" min="0">
                             <x-input-error :messages="$errors->get('target_aht')" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- Dates & Docs Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                     <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Vigencia y Documentación
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="start_date" class="form-label">Fecha Inicio</label>
                            <input type="date" name="start_date" id="start_date" value="{{ old('start_date', optional($campaign->start_date)->format('Y-m-d')) }}" class="form-input">
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>

                         <div class="form-group">
                            <label for="end_date" class="form-label">Fecha Fin</label>
                            <input type="date" name="end_date" id="end_date" value="{{ old('end_date', optional($campaign->end_date)->format('Y-m-d')) }}" class="form-input">
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                    </div>

                    <div class="form-group mb-6">
                        <label for="script_url" class="form-label">Enlace a Script / Guía Operativa (URL)</label>
                        <input type="url" name="script_url" id="script_url" value="{{ old('script_url', $campaign->script_url) }}" 
                            class="form-input" placeholder="https://drive.google.com/...">
                         <x-input-error :messages="$errors->get('script_url')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea name="description" id="description" rows="3" 
                            class="form-textarea">{{ old('description', $campaign->description) }}</textarea>
                         <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4">
                    <a href="{{ route('campaigns.show', $campaign) }}" class="btn-secondary btn-lg">Cancelar</a>
                    <button type="submit" class="btn-primary btn-lg shadow-lg hover:shadow-indigo-500/30 min-w-[200px]">
                        Actualizar Campaña
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
